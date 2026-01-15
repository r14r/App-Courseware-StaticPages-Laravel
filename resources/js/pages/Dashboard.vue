<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';

import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';
import { type BreadcrumbItem } from '@/types';

type CourseSummary = {
    id: number;
    slug: string;
    title: string;
    description: string | null;
    score: number | null;
    total_answers: number | null;
    correct_answers: number | null;
    final_score: number | null;
    chapters: number;
    completed_chapters: number;
    completed_topics: number;
    total_topics: number;
};

const props = defineProps<{
    courses: CourseSummary[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: dashboard().url,
    },
];

const totalCourses = computed(() => props.courses.length);
const totalChapters = computed(() =>
    props.courses.reduce((sum, course) => sum + (course.chapters || 0), 0),
);
const totalCompletedChapters = computed(() =>
    props.courses.reduce((sum, course) => sum + (course.completed_chapters || 0), 0),
);

const completedCourses = computed(() =>
    props.courses.filter((course) => course.chapters > 0 && course.completed_chapters >= course.chapters),
);
const inProgressCourses = computed(() =>
    props.courses.filter(
        (course) =>
            course.chapters > 0 &&
            course.completed_chapters > 0 &&
            course.completed_chapters < course.chapters,
    ),
);
const notStartedCourses = computed(() =>
    props.courses.filter((course) => course.completed_chapters === 0),
);

const overallProgressPercent = computed(() => {
    if (!totalChapters.value) {
        return 0;
    }
    return Math.round((totalCompletedChapters.value / totalChapters.value) * 100);
});

const totalAnswers = computed(() =>
    props.courses.reduce((sum, course) => sum + (course.total_answers || 0), 0),
);
const totalCorrectAnswers = computed(() =>
    props.courses.reduce((sum, course) => sum + (course.correct_answers || 0), 0),
);
const averageQuizPercent = computed(() => {
    if (!totalAnswers.value) {
        return null;
    }
    return Math.round((totalCorrectAnswers.value / totalAnswers.value) * 100);
});

const averageFinalScore = computed(() => {
    const scores = props.courses
        .map((course) => course.final_score)
        .filter((score): score is number => score !== null);
    if (!scores.length) {
        return null;
    }
    return Math.round((scores.reduce((sum, score) => sum + score, 0) / scores.length) * 10) / 10;
});

const progressDash = (percent: number): string => `${percent} ${100 - percent}`;
const percent = (value: number, total: number): number =>
    total > 0 ? Math.round((value / total) * 100) : 0;
const topicsPercent = (course: CourseSummary): number =>
    percent(course.completed_topics, course.total_topics);
const quizPercent = (course: CourseSummary): number => {
    if (course.total_answers !== null && course.total_answers > 0 && course.correct_answers !== null) {
        return Math.round((course.correct_answers / course.total_answers) * 100);
    }
    if (course.final_score !== null) {
        return Math.round(course.final_score);
    }
    return 0;
};
const quizMeta = (course: CourseSummary): string => {
    if (course.total_answers !== null && course.correct_answers !== null) {
        return `${course.correct_answers} / ${course.total_answers}`;
    }
    if (course.final_score !== null) {
        return `${course.final_score}%`;
    }
    return '—';
};
</script>

<template>
    <Head title="Dashboard" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-4">
            <section>
                <div class="flex flex-wrap items-center justify-between gap-4">
                    <div>
                        <p class="text-xs uppercase tracking-[0.35em] text-muted-foreground">Your Courses</p>
                        <h1 class="mt-2 text-2xl font-semibold text-foreground">Progress Overview</h1>
                    </div>
                    <Link
                        href="/"
                        class="rounded-full border border-border px-4 py-2 text-xs font-semibold uppercase tracking-[0.3em] text-foreground"
                    >
                        Browse Courses
                    </Link>
                </div>
            </section>

            <section v-if="courses.length" class="grid gap-4 lg:grid-cols-[1.1fr_1.9fr]">
                <article class="rounded-2xl border border-border bg-card p-5 shadow-sm">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-xs uppercase tracking-[0.35em] text-muted-foreground">Completion Overview</p>
                            <h2 class="mt-2 text-lg font-semibold text-foreground">Course Progress</h2>
                        </div>
                        <span class="rounded-full bg-muted px-3 py-1 text-xs uppercase tracking-[0.3em] text-muted-foreground">
                            {{ totalCourses }} courses
                        </span>
                    </div>
                    <div class="mt-6 flex flex-col items-center gap-6">
                        <div class="relative flex h-44 w-44 items-center justify-center">
                            <svg class="h-44 w-44 -rotate-90">
                                <circle
                                    cx="88"
                                    cy="88"
                                    r="70"
                                    fill="transparent"
                                    stroke-width="16"
                                    class="text-muted"
                                    stroke="currentColor"
                                />
                                <circle
                                    cx="88"
                                    cy="88"
                                    r="70"
                                    fill="transparent"
                                    stroke-width="16"
                                    class="text-emerald-500"
                                    stroke="currentColor"
                                    stroke-linecap="round"
                                    :stroke-dasharray="progressDash(overallProgressPercent)"
                                />
                            </svg>
                            <div class="absolute flex h-24 w-24 flex-col items-center justify-center rounded-full bg-card text-center shadow-inner">
                                <span class="text-3xl font-semibold text-foreground">{{ overallProgressPercent }}%</span>
                                <span class="text-xs uppercase tracking-[0.3em] text-muted-foreground">Complete</span>
                            </div>
                        </div>
                        <div class="flex flex-wrap items-center justify-center gap-3 text-xs text-muted-foreground">
                            <span class="rounded-full bg-muted px-3 py-1 uppercase tracking-[0.3em] text-muted-foreground">
                                {{ totalCompletedChapters }} / {{ totalChapters }} chapters
                            </span>
                            <span class="rounded-full bg-muted px-3 py-1 uppercase tracking-[0.3em] text-muted-foreground">
                                {{ totalCorrectAnswers }} / {{ totalAnswers }} answers
                            </span>
                        </div>
                    </div>
                </article>

                <div class="grid gap-4">
                    <article class="rounded-2xl border border-border bg-card p-5 shadow-sm">
                        <div class="flex flex-wrap items-center justify-between gap-4">
                            <div>
                                <p class="text-xs uppercase tracking-[0.35em] text-muted-foreground">Status Overview</p>
                                <h2 class="mt-2 text-lg font-semibold text-foreground">Course States</h2>
                            </div>
                            <div class="flex flex-wrap items-center gap-3 text-xs uppercase tracking-[0.3em] text-muted-foreground">
                                <span>Completed {{ completedCourses.length }}</span>
                                <span>In progress {{ inProgressCourses.length }}</span>
                                <span>Not started {{ notStartedCourses.length }}</span>
                            </div>
                        </div>

                        <div class="mt-6 grid gap-4 md:grid-cols-3">
                            <div class="flex items-center gap-4 rounded-xl border border-border/60 bg-background/50 p-4">
                                <div class="relative h-16 w-16">
                                    <svg class="h-16 w-16 -rotate-90">
                                        <circle
                                            cx="32"
                                            cy="32"
                                            r="24"
                                            fill="transparent"
                                            stroke-width="8"
                                            class="text-muted"
                                            stroke="currentColor"
                                        />
                                        <circle
                                            cx="32"
                                            cy="32"
                                            r="24"
                                            fill="transparent"
                                            stroke-width="8"
                                            class="text-emerald-500"
                                            stroke="currentColor"
                                            stroke-linecap="round"
                                            :stroke-dasharray="progressDash(percent(completedCourses.length, totalCourses))"
                                        />
                                    </svg>
                                    <div class="absolute inset-0 flex items-center justify-center text-sm font-semibold text-foreground">
                                        {{ percent(completedCourses.length, totalCourses) }}%
                                    </div>
                                </div>
                                <div>
                                    <p class="text-xs uppercase tracking-[0.3em] text-muted-foreground">Completed</p>
                                    <p class="text-lg font-semibold text-foreground">{{ completedCourses.length }}</p>
                                </div>
                            </div>
                            <div class="flex items-center gap-4 rounded-xl border border-border/60 bg-background/50 p-4">
                                <div class="relative h-16 w-16">
                                    <svg class="h-16 w-16 -rotate-90">
                                        <circle
                                            cx="32"
                                            cy="32"
                                            r="24"
                                            fill="transparent"
                                            stroke-width="8"
                                            class="text-muted"
                                            stroke="currentColor"
                                        />
                                        <circle
                                            cx="32"
                                            cy="32"
                                            r="24"
                                            fill="transparent"
                                            stroke-width="8"
                                            class="text-amber-500"
                                            stroke="currentColor"
                                            stroke-linecap="round"
                                            :stroke-dasharray="progressDash(percent(inProgressCourses.length, totalCourses))"
                                        />
                                    </svg>
                                    <div class="absolute inset-0 flex items-center justify-center text-sm font-semibold text-foreground">
                                        {{ percent(inProgressCourses.length, totalCourses) }}%
                                    </div>
                                </div>
                                <div>
                                    <p class="text-xs uppercase tracking-[0.3em] text-muted-foreground">In progress</p>
                                    <p class="text-lg font-semibold text-foreground">{{ inProgressCourses.length }}</p>
                                </div>
                            </div>
                            <div class="flex items-center gap-4 rounded-xl border border-border/60 bg-background/50 p-4">
                                <div class="relative h-16 w-16">
                                    <svg class="h-16 w-16 -rotate-90">
                                        <circle
                                            cx="32"
                                            cy="32"
                                            r="24"
                                            fill="transparent"
                                            stroke-width="8"
                                            class="text-muted"
                                            stroke="currentColor"
                                        />
                                        <circle
                                            cx="32"
                                            cy="32"
                                            r="24"
                                            fill="transparent"
                                            stroke-width="8"
                                            class="text-slate-400"
                                            stroke="currentColor"
                                            stroke-linecap="round"
                                            :stroke-dasharray="progressDash(percent(notStartedCourses.length, totalCourses))"
                                        />
                                    </svg>
                                    <div class="absolute inset-0 flex items-center justify-center text-sm font-semibold text-foreground">
                                        {{ percent(notStartedCourses.length, totalCourses) }}%
                                    </div>
                                </div>
                                <div>
                                    <p class="text-xs uppercase tracking-[0.3em] text-muted-foreground">Not started</p>
                                    <p class="text-lg font-semibold text-foreground">{{ notStartedCourses.length }}</p>
                                </div>
                            </div>
                        </div>

                        <div class="mt-6 space-y-3 text-sm text-muted-foreground">
                            <div class="flex items-center justify-between gap-4">
                                <span>Completed</span>
                                <div class="flex flex-1 items-center gap-3">
                                    <div class="h-2 flex-1 rounded-full bg-muted">
                                        <div
                                            class="h-2 rounded-full bg-emerald-500"
                                            :style="{ width: `${percent(completedCourses.length, totalCourses)}%` }"
                                        ></div>
                                    </div>
                                    <span class="w-10 text-right">{{ completedCourses.length }}</span>
                                </div>
                            </div>
                            <div class="flex items-center justify-between gap-4">
                                <span>In progress</span>
                                <div class="flex flex-1 items-center gap-3">
                                    <div class="h-2 flex-1 rounded-full bg-muted">
                                        <div
                                            class="h-2 rounded-full bg-amber-500"
                                            :style="{ width: `${percent(inProgressCourses.length, totalCourses)}%` }"
                                        ></div>
                                    </div>
                                    <span class="w-10 text-right">{{ inProgressCourses.length }}</span>
                                </div>
                            </div>
                            <div class="flex items-center justify-between gap-4">
                                <span>Not started</span>
                                <div class="flex flex-1 items-center gap-3">
                                    <div class="h-2 flex-1 rounded-full bg-muted">
                                        <div
                                            class="h-2 rounded-full bg-slate-400"
                                            :style="{ width: `${percent(notStartedCourses.length, totalCourses)}%` }"
                                        ></div>
                                    </div>
                                    <span class="w-10 text-right">{{ notStartedCourses.length }}</span>
                                </div>
                            </div>
                        </div>
                    </article>

                    <article class="grid gap-4 md:grid-cols-2">
                        <div class="rounded-2xl border border-border bg-card p-5 shadow-sm">
                            <p class="text-xs uppercase tracking-[0.35em] text-muted-foreground">Average Scores</p>
                            <h3 class="mt-2 text-lg font-semibold text-foreground">Quiz Accuracy</h3>
                            <div class="mt-6 flex items-center gap-6">
                                <div class="relative h-24 w-24">
                                    <svg class="h-24 w-24 -rotate-90">
                                        <circle
                                            cx="48"
                                            cy="48"
                                            r="36"
                                            fill="transparent"
                                            stroke-width="10"
                                            class="text-muted"
                                            stroke="currentColor"
                                        />
                                        <circle
                                            v-if="averageQuizPercent !== null"
                                            cx="48"
                                            cy="48"
                                            r="36"
                                            fill="transparent"
                                            stroke-width="10"
                                            class="text-sky-500"
                                            stroke="currentColor"
                                            stroke-linecap="round"
                                            :stroke-dasharray="progressDash(averageQuizPercent)"
                                        />
                                    </svg>
                                    <div class="absolute inset-0 flex items-center justify-center text-lg font-semibold text-foreground">
                                        <template v-if="averageQuizPercent !== null">{{ averageQuizPercent }}%</template>
                                        <template v-else>—</template>
                                    </div>
                                </div>
                                <div class="space-y-2 text-sm text-muted-foreground">
                                    <p>
                                        Correct answers: <span class="font-semibold text-foreground">{{ totalCorrectAnswers }}</span>
                                    </p>
                                    <p>
                                        Total answers: <span class="font-semibold text-foreground">{{ totalAnswers }}</span>
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div class="rounded-2xl border border-border bg-card p-5 shadow-sm">
                            <p class="text-xs uppercase tracking-[0.35em] text-muted-foreground">Average Final Score</p>
                            <h3 class="mt-2 text-lg font-semibold text-foreground">Course Results</h3>
                            <div class="mt-6 flex items-center gap-6">
                                <div class="relative h-24 w-24">
                                    <svg class="h-24 w-24 -rotate-90">
                                        <circle
                                            cx="48"
                                            cy="48"
                                            r="36"
                                            fill="transparent"
                                            stroke-width="10"
                                            class="text-muted"
                                            stroke="currentColor"
                                        />
                                        <circle
                                            v-if="averageFinalScore !== null"
                                            cx="48"
                                            cy="48"
                                            r="36"
                                            fill="transparent"
                                            stroke-width="10"
                                            class="text-violet-500"
                                            stroke="currentColor"
                                            stroke-linecap="round"
                                            :stroke-dasharray="progressDash(averageFinalScore)"
                                        />
                                    </svg>
                                    <div class="absolute inset-0 flex items-center justify-center text-lg font-semibold text-foreground">
                                        <template v-if="averageFinalScore !== null">{{ averageFinalScore }}%</template>
                                        <template v-else>—</template>
                                    </div>
                                </div>
                                <div class="space-y-2 text-sm text-muted-foreground">
                                    <p>
                                        Completed courses: <span class="font-semibold text-foreground">{{ completedCourses.length }}</span>
                                    </p>
                                    <p>
                                        In progress: <span class="font-semibold text-foreground">{{ inProgressCourses.length }}</span>
                                    </p>
                                </div>
                            </div>
                        </div>
                    </article>
                </div>
            </section>

            <section v-if="courses.length" class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                <article
                    v-for="course in courses"
                    :key="course.id"
                    class="rounded-2xl border border-border bg-card p-5 shadow-sm"
                >
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <h2 class="text-lg font-semibold text-foreground">{{ course.title }}</h2>
                            <p class="mt-2 text-sm text-muted-foreground">
                                {{ course.description || 'No description yet.' }}
                            </p>
                        </div>
                        <span class="rounded-full bg-muted px-3 py-1 text-xs uppercase tracking-[0.3em] text-muted-foreground">
                            {{ course.chapters }} chapters
                        </span>
                    </div>
                    <div class="mt-4 flex flex-wrap items-center gap-3 text-xs text-muted-foreground">
                        <span class="rounded-full bg-muted px-3 py-1 uppercase tracking-[0.3em] text-muted-foreground">
                            {{ course.completed_chapters }} / {{ course.chapters }} chapters
                        </span>
                        <span class="rounded-full bg-muted px-3 py-1 uppercase tracking-[0.3em] text-muted-foreground">
                            {{ course.completed_topics }} / {{ course.total_topics }} topics
                        </span>
                    </div>
                    <div class="mt-6 grid gap-4 sm:grid-cols-2">
                        <div class="rounded-xl border border-border/60 bg-background/50 p-4">
                            <p class="text-xs uppercase tracking-[0.3em] text-muted-foreground">Topics</p>
                            <div class="mt-4 flex items-center gap-4">
                                <div class="relative h-16 w-16">
                                    <svg class="h-16 w-16 -rotate-90">
                                        <circle
                                            cx="32"
                                            cy="32"
                                            r="24"
                                            fill="transparent"
                                            stroke-width="8"
                                            class="text-muted"
                                            stroke="currentColor"
                                        />
                                        <circle
                                            cx="32"
                                            cy="32"
                                            r="24"
                                            fill="transparent"
                                            stroke-width="8"
                                            class="text-emerald-500"
                                            stroke="currentColor"
                                            stroke-linecap="round"
                                            :stroke-dasharray="progressDash(topicsPercent(course))"
                                        />
                                    </svg>
                                    <div class="absolute inset-0 flex items-center justify-center text-sm font-semibold text-foreground">
                                        {{ topicsPercent(course) }}%
                                    </div>
                                </div>
                                <div class="text-sm text-muted-foreground">
                                    <p>
                                        {{ course.completed_topics }} / {{ course.total_topics }} topics
                                    </p>
                                </div>
                            </div>
                        </div>
                        <div class="rounded-xl border border-border/60 bg-background/50 p-4">
                            <p class="text-xs uppercase tracking-[0.3em] text-muted-foreground">Quiz</p>
                            <div class="mt-4 flex items-center gap-4">
                                <div class="relative h-16 w-16">
                                    <svg class="h-16 w-16 -rotate-90">
                                        <circle
                                            cx="32"
                                            cy="32"
                                            r="24"
                                            fill="transparent"
                                            stroke-width="8"
                                            class="text-muted"
                                            stroke="currentColor"
                                        />
                                        <circle
                                            cx="32"
                                            cy="32"
                                            r="24"
                                            fill="transparent"
                                            stroke-width="8"
                                            class="text-sky-500"
                                            stroke="currentColor"
                                            stroke-linecap="round"
                                            :stroke-dasharray="progressDash(quizPercent(course))"
                                        />
                                    </svg>
                                    <div class="absolute inset-0 flex items-center justify-center text-sm font-semibold text-foreground">
                                        {{ quizPercent(course) }}%
                                    </div>
                                </div>
                                <div class="text-sm text-muted-foreground">
                                    <p>{{ quizMeta(course) }}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="mt-4">
                        <Link
                            :href="`/courses/${encodeURIComponent(course.slug)}`"
                            class="text-xs font-semibold uppercase tracking-[0.3em] text-primary"
                        >
                            Open Course
                        </Link>
                    </div>
                </article>
            </section>

            <section v-else class="rounded-2xl border border-dashed border-border bg-card p-8 text-center">
                <p class="text-sm text-muted-foreground">No courses assigned yet.</p>
            </section>
        </div>
    </AppLayout>
</template>
