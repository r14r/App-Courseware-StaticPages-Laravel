<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { computed, onMounted, ref } from 'vue';

type QuizResult = {
    id: string;
    question: string;
    options: string[];
    selectedIndex: number;
    correctIndex: number;
    explanation?: string;
};

type QuizPayload = {
    title: string;
    slug: string;
    chapter: string;
    total: number;
    score: number;
    results: QuizResult[];
};

const props = defineProps<{ slug: string; chapter: string }>();

const payload = ref<QuizPayload | null>(null);

const title = computed(() => payload.value?.title || 'Quiz Results');
const courseLink = computed(() => `/courses/${encodeURIComponent(props.slug)}`);
const quizLink = computed(
    () => `/courses/${encodeURIComponent(props.slug)}/chapters/${encodeURIComponent(props.chapter)}/quiz`,
);

const progressPercent = computed(() => {
    if (!payload.value?.total) {
        return 0;
    }
    return Math.round((payload.value.score / payload.value.total) * 100);
});

function isPerfect(): boolean {
    return Boolean(payload.value && payload.value.score === payload.value.total);
}

function isCorrect(result: QuizResult): boolean {
    return Number(result.selectedIndex) === Number(result.correctIndex);
}

function selectedLabel(result: QuizResult): string {
    return result.options?.[result.selectedIndex] || '—';
}

function correctLabel(result: QuizResult): string {
    return result.options?.[result.correctIndex] || '—';
}

onMounted(() => {
    const key = `quizResults:${props.slug}:${props.chapter}`;
    const stored = sessionStorage.getItem(key);
    if (!stored) {
        payload.value = null;
        return;
    }
    try {
        payload.value = JSON.parse(stored) as QuizPayload;
    } catch {
        payload.value = null;
    }
});
</script>

<template>
    <Head :title="title" />

    <div class="min-h-screen bg-background text-foreground">
        <header class="border-b border-border bg-background/80 backdrop-blur">
            <div class="mx-auto flex w-full max-w-4xl flex-wrap items-center justify-between gap-4 px-6 py-5">
                <div>
                    <p class="text-xs uppercase tracking-[0.35em] text-muted-foreground">Results</p>
                    <h1 class="mt-2 text-2xl font-semibold text-foreground">{{ title }}</h1>
                </div>
                <div class="flex flex-wrap gap-2">
                    <Link
                        class="rounded-full border border-border px-4 py-2 text-xs uppercase tracking-[0.3em] text-muted-foreground hover:text-foreground"
                        :href="courseLink"
                    >
                        Back to course
                    </Link>
                    <Link
                        class="rounded-full bg-foreground px-4 py-2 text-xs font-semibold uppercase tracking-[0.3em] text-background"
                        :href="quizLink"
                    >
                        Back to quiz
                    </Link>
                </div>
            </div>
        </header>

        <main class="mx-auto w-full max-w-4xl px-6 py-10">
            <div v-if="!payload" class="rounded-2xl border border-border bg-card p-6">
                <p class="text-sm text-muted-foreground">No quiz results found.</p>
                <Link
                    class="mt-4 inline-flex rounded-full bg-foreground px-4 py-2 text-xs font-semibold uppercase tracking-[0.3em] text-background"
                    :href="quizLink"
                >
                    Go to quiz
                </Link>
            </div>

            <div v-else class="space-y-6">
                <div class="relative overflow-hidden rounded-2xl border border-border bg-card p-6">
                    <div v-if="isPerfect()" class="balloon-burst" aria-hidden="true">
                        <span class="balloon balloon--one"></span>
                        <span class="balloon balloon--two"></span>
                        <span class="balloon balloon--three"></span>
                        <span class="balloon balloon--four"></span>
                        <span class="balloon balloon--five"></span>
                    </div>
                    <div class="flex flex-wrap items-center justify-between gap-4">
                        <div>
                            <p class="text-xs uppercase tracking-[0.35em] text-muted-foreground">Score</p>
                            <div class="mt-2 text-3xl font-semibold text-foreground">
                                {{ payload.score }} / {{ payload.total }}
                            </div>
                        </div>
                        <div class="w-full max-w-xs">
                            <div class="h-2 rounded-full bg-muted/60">
                                <div
                                    class="h-2 rounded-full bg-foreground"
                                    :style="{ width: `${progressPercent}%` }"
                                ></div>
                            </div>
                            <p class="mt-2 text-xs uppercase tracking-[0.3em] text-muted-foreground">
                                {{ progressPercent }}% complete
                            </p>
                        </div>
                    </div>
                </div>

                <div class="space-y-4">
                    <article
                        v-for="(result, index) in payload.results"
                        :key="result.id"
                        class="rounded-2xl border border-border bg-card p-5"
                    >
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <p class="text-xs uppercase tracking-[0.3em] text-muted-foreground">Question {{ index + 1 }}</p>
                                <h2 class="mt-2 text-base font-semibold text-foreground">
                                    {{ result.question }}
                                </h2>
                            </div>
                            <span
                                class="rounded-full px-3 py-1 text-xs uppercase tracking-[0.3em]"
                                :class="isCorrect(result) ? 'bg-emerald-400/20 text-emerald-200' : 'bg-rose-400/20 text-rose-200'"
                            >
                                {{ isCorrect(result) ? 'Correct' : 'Incorrect' }}
                            </span>
                        </div>
                        <div class="mt-4 text-sm text-muted-foreground">
                            <p>
                                Selected: <span class="font-semibold text-foreground">{{ selectedLabel(result) }}</span>
                            </p>
                            <p>
                                Correct: <span class="font-semibold text-emerald-200">{{ correctLabel(result) }}</span>
                            </p>
                            <p v-if="result.explanation" class="mt-2 text-muted-foreground">
                                {{ result.explanation }}
                            </p>
                        </div>
                    </article>
                </div>
            </div>
        </main>
    </div>
</template>

<style scoped>
.balloon-burst {
    position: absolute;
    inset: 0;
    pointer-events: none;
    overflow: hidden;
    z-index: 5;
}

.balloon {
    position: absolute;
    bottom: -120px;
    width: 56px;
    height: 70px;
    border-radius: 50% 50% 45% 45%;
    opacity: 0.9;
    animation: floatUp 5.5s ease-in infinite;
}

.balloon::after {
    content: '';
    position: absolute;
    left: 50%;
    top: 68px;
    width: 2px;
    height: 40px;
    background: rgba(0, 0, 0, 0.2);
    transform: translateX(-50%);
}

.balloon--one {
    left: 8%;
    background: #f18f60;
    animation-delay: 0s;
}

.balloon--two {
    left: 28%;
    background: #5fb3b3;
    animation-delay: 0.8s;
    width: 50px;
    height: 64px;
}

.balloon--three {
    left: 52%;
    background: #f2b705;
    animation-delay: 0.3s;
    width: 60px;
    height: 74px;
}

.balloon--four {
    left: 72%;
    background: #7b6ff2;
    animation-delay: 1.1s;
}

.balloon--five {
    left: 90%;
    background: #ef476f;
    animation-delay: 0.5s;
    width: 48px;
    height: 62px;
}

@keyframes floatUp {
    0% {
        transform: translateY(0) translateX(0) rotate(0deg);
    }
    50% {
        transform: translateY(-60vh) translateX(-10px) rotate(-4deg);
    }
    100% {
        transform: translateY(-120vh) translateX(10px) rotate(6deg);
    }
}
</style>
