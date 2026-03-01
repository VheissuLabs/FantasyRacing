<script setup lang="ts">
    import { Form } from '@inertiajs/vue3'
    import { ref, computed } from 'vue'
    import { regenerateInviteCode } from '@/actions/App/Http/Controllers/Leagues/LeagueSettingsController'
    import InputError from '@/components/InputError.vue'
    import { Button } from '@/components/ui/button'
    import {
        Card,
        CardContent,
        CardDescription,
        CardFooter,
        CardHeader,
        CardTitle,
    } from '@/components/ui/card'
    import { Checkbox } from '@/components/ui/checkbox'
    import { Input } from '@/components/ui/input'
    import { Label } from '@/components/ui/label'
    import { Separator } from '@/components/ui/separator'

    interface Franchise {
        id: number
        name: string
        slug: string
    }

    interface FormData {
        franchise_id?: string | number
        name: string
        description: string
        max_teams: string | number
        visibility: string
        join_policy: string
        rules: {
            no_duplicates: boolean
            trade_approval_required: boolean
            trades_enabled: boolean
            max_roster_size: string | number
        }
        processing: boolean
        errors: Record<string, string>
    }

    const form = defineModel<FormData>('form', { required: true })

    const props = defineProps<{
        title: string
        description?: string
        submitLabel: string
        submittingLabel: string
        franchises: Franchise[]
        inviteCode?: string | null
        leagueSlug?: string
    }>()

    defineEmits<{
        submit: []
    }>()

    const copied = ref(false)

    const inviteUrl = computed(() =>
        props.inviteCode ? `${window.location.origin}/join/${props.inviteCode}` : '',
    )

    function copyInviteLink() {
        if (inviteUrl.value) {
            navigator.clipboard.writeText(inviteUrl.value)
            copied.value = true
            setTimeout(() => (copied.value = false), 2000)
        }
    }
</script>

<template>
    <form @submit.prevent="$emit('submit')">
        <Card>
            <CardHeader>
                <CardTitle>{{ title }}</CardTitle>
                <CardDescription v-if="description">
                    {{ description }}
                </CardDescription>
            </CardHeader>
            <CardContent class="space-y-6">
                <!-- Franchise -->
                <div class="grid gap-2">
                    <Label for="franchise_id">Franchise</Label>
                    <select
                        id="franchise_id"
                        v-model="form.franchise_id"
                        required
                        :disabled="!!leagueSlug"
                        class="flex h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-sm shadow-xs ring-offset-background placeholder:text-muted-foreground focus-visible:ring-1 focus-visible:ring-ring focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50"
                    >
                        <option value="">Select franchise...</option>
                        <option
                            v-for="franchise in franchises"
                            :key="franchise.id"
                            :value="franchise.id"
                        >
                            {{ franchise.name }}
                        </option>
                    </select>
                    <InputError :message="form.errors.franchise_id" />
                </div>

                <!-- Name -->
                <div class="grid gap-2">
                    <Label for="name">League Name</Label>
                    <Input
                        id="name"
                        v-model="form.name"
                        type="text"
                        required
                        maxlength="255"
                        placeholder="My Fantasy League"
                    />
                    <InputError :message="form.errors.name" />
                </div>

                <!-- Description -->
                <div class="grid gap-2">
                    <Label for="description">
                        Description
                        <span class="text-muted-foreground">(optional)</span>
                    </Label>
                    <textarea
                        id="description"
                        v-model="form.description"
                        rows="3"
                        maxlength="1000"
                        class="flex w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-xs ring-offset-background placeholder:text-muted-foreground focus-visible:ring-1 focus-visible:ring-ring focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50"
                    />
                    <InputError :message="form.errors.description" />
                </div>

                <!-- Visibility -->
                <div class="grid gap-2">
                    <Label>Visibility</Label>
                    <div class="flex gap-4">
                        <Label
                            class="flex cursor-pointer items-center gap-2 font-normal"
                        >
                            <input
                                type="radio"
                                v-model="form.visibility"
                                value="public"
                            />
                            Public
                        </Label>
                        <Label
                            class="flex cursor-pointer items-center gap-2 font-normal"
                        >
                            <input
                                type="radio"
                                v-model="form.visibility"
                                value="private"
                            />
                            Private
                        </Label>
                    </div>
                    <InputError :message="form.errors.visibility" />
                </div>

                <!-- Join policy -->
                <div class="grid gap-2">
                    <Label>Join Policy</Label>
                    <div class="flex flex-wrap gap-4">
                        <Label
                            class="flex cursor-pointer items-center gap-2 font-normal"
                        >
                            <input
                                type="radio"
                                v-model="form.join_policy"
                                value="open"
                            />
                            Open
                        </Label>
                        <Label
                            class="flex cursor-pointer items-center gap-2 font-normal"
                        >
                            <input
                                type="radio"
                                v-model="form.join_policy"
                                value="request"
                            />
                            Request
                        </Label>
                        <Label
                            class="flex cursor-pointer items-center gap-2 font-normal"
                        >
                            <input
                                type="radio"
                                v-model="form.join_policy"
                                value="invite_only"
                            />
                            Invite Only
                        </Label>
                    </div>
                    <InputError :message="form.errors.join_policy" />
                </div>

                <!-- Invite code (edit only, when invite_only) -->
                <div
                    v-if="inviteCode && form.join_policy === 'invite_only'"
                    class="grid gap-2"
                >
                    <Label>Invite Code Link</Label>
                    <div class="flex gap-2">
                        <Input
                            :model-value="inviteUrl"
                            readonly
                            class="min-w-0 flex-1 font-mono text-xs"
                        />
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            @click="copyInviteLink"
                        >
                            {{ copied ? 'Copied!' : 'Copy' }}
                        </Button>
                    </div>
                    <div class="flex items-center gap-2">
                        <Form
                            :action="
                                regenerateInviteCode({
                                    league: leagueSlug!,
                                }).url
                            "
                            method="post"
                            #default="{ processing }"
                            class="inline"
                        >
                            <Button
                                type="submit"
                                :disabled="processing"
                                variant="link"
                                size="sm"
                                class="h-auto p-0 text-xs"
                            >
                                {{
                                    processing
                                        ? 'Regenerating...'
                                        : 'Regenerate invite code'
                                }}
                            </Button>
                        </Form>
                        <span class="text-xs text-muted-foreground">
                            The old link will stop working.
                        </span>
                    </div>
                </div>
                <p
                    v-else-if="
                        form.join_policy === 'invite_only' &&
                        !inviteCode &&
                        leagueSlug
                    "
                    class="text-xs text-muted-foreground"
                >
                    An invite code will be generated when you save.
                </p>

                <Separator />

                <!-- Rules -->
                <div class="space-y-4">
                    <Label>League Rules</Label>

                    <Label
                        for="no_duplicates"
                        class="flex items-center gap-3 font-normal"
                    >
                        <Checkbox
                            id="no_duplicates"
                            :checked="form.rules.no_duplicates"
                            @update:checked="
                                (v: boolean) => {
                                    form.rules.no_duplicates = v
                                    if (v) form.max_teams = 7
                                }
                            "
                        />
                        No duplicate picks (each entity can only be on one team)
                    </Label>
                    <p
                        v-if="form.rules.no_duplicates"
                        class="text-xs text-muted-foreground"
                    >
                        Maximum of 7 fantasy teams.
                    </p>

                    <Label
                        for="trades_enabled"
                        class="flex items-center gap-3 font-normal"
                    >
                        <Checkbox
                            id="trades_enabled"
                            :checked="form.rules.trades_enabled"
                            @update:checked="
                                (v: boolean) => (form.rules.trades_enabled = v)
                            "
                        />
                        Trades enabled
                    </Label>

                    <Label
                        for="trade_approval"
                        class="flex items-center gap-3 font-normal"
                    >
                        <Checkbox
                            id="trade_approval"
                            :checked="form.rules.trade_approval_required"
                            @update:checked="
                                (v: boolean) =>
                                    (form.rules.trade_approval_required = v)
                            "
                        />
                        Commissioner approval required for trades
                    </Label>

                    <!-- Max teams -->
                    <div class="grid gap-2">
                        <Label for="max_teams">
                            Max Teams
                            <span class="text-muted-foreground">
                                (optional)
                            </span>
                        </Label>
                        <Input
                            id="max_teams"
                            v-model="form.max_teams"
                            type="number"
                            min="2"
                            :max="form.rules.no_duplicates ? 7 : 20"
                            :disabled="form.rules.no_duplicates"
                            :placeholder="
                                form.rules.no_duplicates ? '7' : 'Unlimited'
                            "
                        />
                        <InputError :message="form.errors.max_teams" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="max_roster_size">
                            Max Roster Size
                            <span class="text-muted-foreground">
                                (optional)
                            </span>
                        </Label>
                        <Input
                            id="max_roster_size"
                            v-model="form.rules.max_roster_size"
                            type="number"
                            min="1"
                            placeholder="Unlimited"
                        />
                        <InputError
                            :message="
                                (form.errors as any)['rules.max_roster_size']
                            "
                        />
                    </div>
                </div>

            </CardContent>

            <CardFooter class="justify-end">
                <Button :disabled="form.processing">
                    {{ form.processing ? submittingLabel : submitLabel }}
                </Button>
            </CardFooter>
        </Card>
    </form>
</template>
