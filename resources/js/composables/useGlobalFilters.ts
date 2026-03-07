import { computed } from 'vue'
import { router, usePage } from '@inertiajs/vue3'

interface Season {
    id: number
    name: string
    year: number
}

interface Franchise {
    id: number
    name: string
    slug: string
    seasons: Season[]
}

interface GlobalFilters {
    franchise: string | null
    seasonId: number | null
}

export function useGlobalFilters() {
    const page = usePage()

    const franchises = computed<Franchise[]>(
        () => (page.props.franchises as Franchise[]) ?? [],
    )

    const filters = computed<GlobalFilters>(
        () => (page.props.globalFilters as GlobalFilters) ?? { franchise: null, seasonId: null },
    )

    const selectedFranchise = computed(() =>
        filters.value.franchise
            ? franchises.value.find((f) => f.slug === filters.value.franchise) ?? null
            : null,
    )

    const availableSeasons = computed(() => {
        if (selectedFranchise.value) {
            return selectedFranchise.value.seasons
        }
        // No franchise selected — show unique seasons across all franchises, deduped by year
        const seen = new Map<number, Season>()
        for (const franchise of franchises.value) {
            for (const season of franchise.seasons) {
                if (!seen.has(season.year)) {
                    seen.set(season.year, season)
                }
            }
        }
        return Array.from(seen.values()).sort((a, b) => b.year - a.year)
    })

    const selectedSeasonId = computed(() => {
        if (!filters.value.seasonId) return null
        const exists = availableSeasons.value.some((s) => s.id === filters.value.seasonId)
        return exists ? filters.value.seasonId : null
    })

    function setFranchise(slug: string | null) {
        document.cookie = `franchise=${slug ?? ''}; path=/; max-age=31536000; SameSite=Lax`
        // Clear season when franchise changes
        document.cookie = `season_id=; path=/; max-age=31536000; SameSite=Lax`
        router.reload()
    }

    function setSeason(seasonId: number | null) {
        document.cookie = `season_id=${seasonId ?? ''}; path=/; max-age=31536000; SameSite=Lax`
        router.reload()
    }

    return {
        franchises,
        filters,
        selectedFranchise,
        availableSeasons,
        selectedSeasonId,
        setFranchise,
        setSeason,
    }
}
