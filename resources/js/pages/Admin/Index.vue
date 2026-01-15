<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { computed, onMounted, ref } from 'vue';

import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';
import type { BreadcrumbItem } from '@/types';

type UserRow = {
    id: number;
    name: string;
    email: string;
    user_type: string;
    created_at: string;
};

type CourseRow = {
    id: number;
    slug: string;
    title: string;
    description: string | null;
    chapters_count: number;
};

type ChapterRow = {
    id: number;
    course_id: number;
    slug: string;
    title: string;
    position: number;
    topics_count: number;
    course?: {
        title: string;
    } | null;
};

type TopicRow = {
    id: number;
    chapter_id: number;
    slug: string;
    title: string;
    position: number;
    chapter?: {
        course?: {
            title: string;
        } | null;
        title?: string;
    } | null;
};

const props = defineProps<{
    users: UserRow[];
    courses: CourseRow[];
    chapters: ChapterRow[];
    topics: TopicRow[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: dashboard().url },
    { title: 'Admin', href: '/admin' },
];

const syncStatus = ref<string | null>(null);
const isSyncing = ref(false);
const showDetails = ref(false);
const isSavingUser = ref<Record<number, boolean>>({});
const users = ref<UserRow[]>([...props.users]);
const userTypes = ['Admin', 'Trainer', 'Student'];
const selectedCourseId = ref<number | null>(null);
const selectedChapterId = ref<number | null>(null);

const userTypeEdits = ref<Record<number, string>>(
    users.value.reduce((acc, user) => {
        acc[user.id] = user.user_type;
        return acc;
    }, {} as Record<number, string>)
);

const csrfToken = (): string | null =>
    document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? null;

const postJson = async (path: string): Promise<void> => {
    const token = csrfToken();
    if (!token) {
        syncStatus.value = 'Missing CSRF token.';
        return;
    }

    isSyncing.value = true;
    syncStatus.value = null;

    const response = await fetch(path, {
        method: 'post',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': token,
        },
        body: JSON.stringify({}),
    });

    const payload = await response.json().catch(() => ({}));
    syncStatus.value = response.ok ? 'Sync completed.' : payload.message || 'Sync failed.';
    isSyncing.value = false;
};

const syncCourses = async (): Promise<void> => postJson('/admin/sync/courses');
const syncTopics = async (): Promise<void> => postJson('/admin/sync/topics');

const patchJson = async (path: string, payload: Record<string, unknown>): Promise<Response> => {
    const token = csrfToken();
    if (!token) {
        syncStatus.value = 'Missing CSRF token.';
        return new Response(null, { status: 419 });
    }

    return fetch(path, {
        method: 'patch',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': token,
        },
        body: JSON.stringify(payload),
    });
};

const saveUserType = async (userId: number): Promise<void> => {
    const selectedType = userTypeEdits.value[userId];
    if (!selectedType) {
        return;
    }

    isSavingUser.value[userId] = true;

    const response = await patchJson(`/admin/users/${userId}`, {
        user_type: selectedType,
    });

    if (response.ok) {
        const payload = (await response.json().catch(() => null)) as { user?: UserRow } | null;
        const user = users.value.find((entry) => entry.id === userId);
        if (user && payload?.user?.user_type) {
            user.user_type = payload.user.user_type;
        }
    } else {
        const payload = (await response.json().catch(() => null)) as { message?: string } | null;
        syncStatus.value = payload?.message ?? 'Failed to update user type.';
    }

    isSavingUser.value[userId] = false;
};

const selectCourse = (courseId: number): void => {
    selectedCourseId.value = courseId;
    selectedChapterId.value = null;
};

const selectChapter = (chapterId: number): void => {
    selectedChapterId.value = chapterId;
};

const filteredChapters = computed(() => {
    if (!selectedCourseId.value) {
        return props.chapters;
    }

    return props.chapters.filter((chapter) => chapter.course_id === selectedCourseId.value);
});

const filteredTopics = computed(() => {
    if (!selectedChapterId.value) {
        return props.topics;
    }

    return props.topics.filter((topic) => topic.chapter_id === selectedChapterId.value);
});

onMounted(() => {
    syncStatus.value = null;
});
</script>

<template>
    <Head title="Admin" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-1 flex-col gap-6 p-4">
            <section class="rounded-2xl border border-border bg-card p-6 shadow-sm">
                <div class="flex flex-wrap items-center justify-between gap-4">
                    <div>
                        <p class="text-xs uppercase tracking-[0.35em] text-muted-foreground">Admin</p>
                        <h1 class="mt-2 text-2xl font-semibold text-foreground">Course Data Sync</h1>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        <button
                            type="button"
                            class="rounded-full border border-border px-4 py-2 text-xs uppercase tracking-[0.3em] text-muted-foreground transition hover:text-foreground"
                            @click="showDetails = !showDetails"
                        >
                            {{ showDetails ? 'Hide Details' : 'Show Details' }}
                        </button>
                        <button
                            type="button"
                            class="rounded-full border border-border px-4 py-2 text-xs uppercase tracking-[0.3em] text-muted-foreground transition hover:text-foreground"
                            :disabled="isSyncing"
                            @click="syncCourses"
                        >
                            Sync Courses
                        </button>
                        <button
                            type="button"
                            class="rounded-full border border-border px-4 py-2 text-xs uppercase tracking-[0.3em] text-muted-foreground transition hover:text-foreground"
                            :disabled="isSyncing"
                            @click="syncTopics"
                        >
                            Sync Topics
                        </button>
                    </div>
                </div>
                <p v-if="syncStatus" class="mt-4 text-sm text-muted-foreground">
                    {{ syncStatus }}
                </p>
            </section>

            <section class="rounded-2xl border border-border bg-card p-6 shadow-sm">
                <h2 class="text-sm font-semibold text-foreground">Users</h2>
                <div class="mt-4 overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="text-xs uppercase tracking-[0.3em] text-muted-foreground">
                            <tr>
                                <th class="py-2 pr-4">Name</th>
                                <th class="py-2 pr-4">Email</th>
                                <th class="py-2 pr-4">Type</th>
                                <th class="py-2 pr-4"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="user in users" :key="user.id" class="border-b border-border/60">
                                <td class="py-2 pr-4 font-medium text-foreground">{{ user.name }}</td>
                                <td class="py-2 pr-4 text-muted-foreground">{{ user.email }}</td>
                                <td class="py-2 pr-4 text-muted-foreground">
                                    <select
                                        v-model="userTypeEdits[user.id]"
                                        class="w-full rounded-md border border-border bg-transparent px-2 py-1 text-xs text-foreground"
                                    >
                                        <option v-for="option in userTypes" :key="option" :value="option">
                                            {{ option }}
                                        </option>
                                    </select>
                                </td>
                                <td class="py-2 pr-4 text-right">
                                    <button
                                        type="button"
                                        class="rounded-full border border-border px-3 py-1 text-[0.65rem] uppercase tracking-[0.3em] text-muted-foreground transition hover:text-foreground"
                                        :disabled="isSavingUser[user.id]"
                                        @click="saveUserType(user.id)"
                                    >
                                        Save
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="grid gap-6 lg:grid-cols-3">
                <div class="rounded-2xl border border-border bg-card p-6 shadow-sm">
                    <h2 class="text-sm font-semibold text-foreground">Courses</h2>
                    <div class="mt-4 overflow-x-auto">
                        <table class="w-full text-left text-sm">
                            <thead class="text-xs uppercase tracking-[0.3em] text-muted-foreground">
                                <tr>
                                    <th class="py-2 pr-4">Title</th>
                                    <th v-if="showDetails" class="py-2 pr-4">Slug</th>
                                    <th v-if="showDetails" class="py-2 pr-4">Chapters</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="course in props.courses"
                                    :key="course.id"
                                    class="cursor-pointer border-b border-border/60 transition hover:bg-muted/40"
                                    :class="{
                                        'bg-muted/60 text-foreground': selectedCourseId === course.id,
                                    }"
                                    @click="selectCourse(course.id)"
                                >
                                    <td class="py-2 pr-4 font-medium text-foreground">{{ course.title }}</td>
                                    <td v-if="showDetails" class="py-2 pr-4 text-muted-foreground">
                                        {{ course.slug }}
                                    </td>
                                    <td v-if="showDetails" class="py-2 pr-4 text-muted-foreground">
                                        {{ course.chapters_count }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="rounded-2xl border border-border bg-card p-6 shadow-sm">
                    <h2 class="text-sm font-semibold text-foreground">Chapters</h2>
                    <div class="mt-4 overflow-x-auto">
                        <table class="w-full text-left text-sm">
                            <thead class="text-xs uppercase tracking-[0.3em] text-muted-foreground">
                                <tr>
                                    <th class="py-2 pr-4">Title</th>
                                    <th v-if="showDetails" class="py-2 pr-4">Slug</th>
                                    <th v-if="showDetails" class="py-2 pr-4">Topics</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="chapter in filteredChapters"
                                    :key="chapter.id"
                                    class="cursor-pointer border-b border-border/60 transition hover:bg-muted/40"
                                    :class="{
                                        'bg-muted/60 text-foreground': selectedChapterId === chapter.id,
                                    }"
                                    @click="selectChapter(chapter.id)"
                                >
                                    <td class="py-2 pr-4 font-medium text-foreground">{{ chapter.title }}</td>
                                    <td v-if="showDetails" class="py-2 pr-4 text-muted-foreground">
                                        {{ chapter.slug }}
                                    </td>
                                    <td v-if="showDetails" class="py-2 pr-4 text-muted-foreground">
                                        {{ chapter.topics_count }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="rounded-2xl border border-border bg-card p-6 shadow-sm">
                    <h2 class="text-sm font-semibold text-foreground">Topics</h2>
                    <div class="mt-4 overflow-x-auto">
                        <table class="w-full text-left text-sm">
                            <thead class="text-xs uppercase tracking-[0.3em] text-muted-foreground">
                                <tr>
                                    <th class="py-2 pr-4">Title</th>
                                    <th v-if="showDetails" class="py-2 pr-4">Slug</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="topic in filteredTopics" :key="topic.id" class="border-b border-border/60">
                                    <td class="py-2 pr-4 font-medium text-foreground">{{ topic.title }}</td>
                                    <td v-if="showDetails" class="py-2 pr-4 text-muted-foreground">{{ topic.slug }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>
        </div>
    </AppLayout>
</template>
