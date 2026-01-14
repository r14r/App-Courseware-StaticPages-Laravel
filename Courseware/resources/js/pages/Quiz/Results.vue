<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';

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

function progressPercent(): number {
    if (!payload.value?.total) {
        return 0;
    }
    return Math.round((payload.value.score / payload.value.total) * 100);
}

function progressStyle(): string {
    return `width: ${progressPercent()}%`;
}

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

function setBodyClass(isActive: boolean): void {
    const className = 'courseware-body';
    if (isActive) {
        document.body.classList.add(className);
    } else {
        document.body.classList.remove(className);
    }
}

onMounted(() => {
    setBodyClass(true);
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

onBeforeUnmount(() => {
    setBodyClass(false);
});
</script>

<template>
    <Head :title="title">
        <link
            rel="stylesheet"
            href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        />
        <link rel="stylesheet" href="/courseware.css" />
    </Head>

    <div class="bg-light">
        <header class="bg-white border-bottom">
            <div class="container py-3 d-flex align-items-center justify-content-between">
                <h1 class="h5 mb-0">{{ title }}</h1>
                <div class="d-flex gap-2">
                    <Link class="btn btn-outline-secondary btn-sm" :href="courseLink">Back to course</Link>
                    <Link class="btn btn-primary btn-sm" :href="quizLink">Back to quiz</Link>
                </div>
            </div>
        </header>

        <main class="container py-4">
            <div v-if="!payload" class="card">
                <div class="card-body">
                    <p class="mb-2">No quiz results found.</p>
                    <Link class="btn btn-primary btn-sm" :href="quizLink">Go to quiz</Link>
                </div>
            </div>

            <div v-else class="card position-relative overflow-hidden">
                <div v-if="isPerfect()" class="balloon-burst" aria-hidden="true">
                    <span class="balloon balloon--one"></span>
                    <span class="balloon balloon--two"></span>
                    <span class="balloon balloon--three"></span>
                    <span class="balloon balloon--four"></span>
                    <span class="balloon balloon--five"></span>
                </div>
                <div class="card-body">
                    <div class="mb-4">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <div class="fw-semibold">Score</div>
                            <div><strong>{{ payload.score }}</strong> / {{ payload.total }}</div>
                        </div>
                        <div class="progress" role="progressbar" :aria-valuenow="payload.score" :aria-valuemax="payload.total">
                            <div class="progress-bar" :style="progressStyle()">
                                {{ progressPercent() }}%
                            </div>
                        </div>
                    </div>

                    <div v-for="(result, resultIndex) in payload.results" :key="result.id" class="card mb-3">
                        <div class="card-body">
                            <div class="d-flex align-items-center gap-2">
                                <svg
                                    v-if="isCorrect(result)"
                                    class="text-success"
                                    width="20"
                                    height="20"
                                    viewBox="0 0 16 16"
                                    fill="currentColor"
                                    aria-hidden="true"
                                >
                                    <path
                                        d="M13.485 1.929a1 1 0 0 1 .086 1.414l-7 8a1 1 0 0 1-1.497.036L2.43 8.733a1 1 0 1 1 1.414-1.414l1.77 1.77 6.293-7.19a1 1 0 0 1 1.414-.086z"
                                    />
                                </svg>
                                <svg
                                    v-else
                                    class="text-danger"
                                    width="20"
                                    height="20"
                                    viewBox="0 0 16 16"
                                    fill="currentColor"
                                    aria-hidden="true"
                                >
                                    <path
                                        d="M4.646 4.646a.5.5 0 0 1 .708 0L8 7.293l2.646-2.647a.5.5 0 0 1 .708.708L8.707 8l2.647 2.646a.5.5 0 0 1-.708.708L8 8.707l-2.646 2.647a.5.5 0 0 1-.708-.708L7.293 8 4.646 5.354a.5.5 0 0 1 0-.708z"
                                    />
                                </svg>
                                <div class="fw-medium">{{ resultIndex + 1 }}. {{ result.question }}</div>
                            </div>
                            <div class="mt-2">
                                <div>
                                    Selected: <span class="fw-semibold">{{ selectedLabel(result) }}</span>
                                </div>
                                <div>
                                    Correct: <span class="fw-semibold text-success">{{ correctLabel(result) }}</span>
                                </div>
                                <div v-if="result.explanation" class="mt-2 text-muted">
                                    {{ result.explanation }}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</template>
