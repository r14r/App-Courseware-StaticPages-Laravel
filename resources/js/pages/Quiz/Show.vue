<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, onMounted, reactive, ref } from 'vue';

type QuizQuestion = {
    id: string;
    type?: string;
    question: string;
    options: string[];
    correctIndex: number;
    explanation?: string;
};

type Quiz = {
    title: string;
    questions: QuizQuestion[];
};

type RawQuiz = {
    title?: string;
    questions?: QuizQuestion[];
    quiz?: {
        title?: string;
        questions?: Array<{
            id: string;
            type?: string;
            question: string;
            options?: string[];
            choices?: string[];
            correctIndex?: number;
            answerIndex?: number;
            explanation?: string;
        }>;
    };
};

const props = defineProps<{ slug: string; chapter: string }>();

const quiz = ref<Quiz | null>(null);
const answers = reactive<Record<string, number>>({});
const currentIndex = ref(0);

const title = computed(() => quiz.value?.title || 'Quiz');
const courseLink = computed(() => `/courses/${encodeURIComponent(props.slug)}`);
const resultsLink = computed(
    () =>
        `/courses/${encodeURIComponent(props.slug)}/chapters/${encodeURIComponent(props.chapter)}/results`,
);
const currentQuestion = computed(() => {
    if (!quiz.value) {
        return null;
    }
    return quiz.value.questions[currentIndex.value] || null;
});

async function fetchJson<T>(path: string): Promise<T | null> {
    try {
        const response = await fetch(path, {
            headers: {
                Accept: 'application/json',
            },
        });
        if (!response.ok) {
            return null;
        }
        return (await response.json()) as T;
    } catch {
        return null;
    }
}

function normalizeQuiz(raw: RawQuiz | null): Quiz | null {
    if (!raw) {
        return null;
    }
    if (Array.isArray(raw.questions)) {
        return {
            title: raw.title || 'Quiz',
            questions: raw.questions.map((question) => ({
                ...question,
                options: question.options || [],
                correctIndex: question.correctIndex ?? 0,
            })),
        };
    }
    if (raw.quiz && Array.isArray(raw.quiz.questions)) {
        return {
            title: raw.title || raw.quiz.title || 'Quiz',
            questions: raw.quiz.questions.map((question) => ({
                id: question.id,
                type: question.type || 'single',
                question: question.question,
                options: question.options || question.choices || [],
                correctIndex: question.correctIndex ?? question.answerIndex ?? 0,
                explanation: question.explanation || '',
            })),
        };
    }
    return null;
}

function progressPercent(): number {
    if (!quiz.value || !quiz.value.questions.length) {
        return 0;
    }
    return Math.round(((currentIndex.value + 1) / quiz.value.questions.length) * 100);
}

function nextLabel(): string {
    if (!quiz.value) {
        return 'Next';
    }
    return currentIndex.value === quiz.value.questions.length - 1 ? 'Submit Quiz' : 'Next';
}

function handleNext(): void {
    if (!quiz.value) {
        return;
    }
    const question = currentQuestion.value;
    if (!question) {
        return;
    }
    if (answers[question.id] === undefined) {
        window.alert('Please answer the question');
        return;
    }
    if (currentIndex.value < quiz.value.questions.length - 1) {
        currentIndex.value += 1;
        return;
    }
    submitQuiz();
}

function submitQuiz(): void {
    if (!quiz.value) {
        return;
    }
    const unanswered = quiz.value.questions.some((question) => answers[question.id] === undefined);
    if (unanswered) {
        window.alert('Please answer all questions');
        return;
    }

    let score = 0;
    const results = quiz.value.questions.map((question) => {
        const selectedIndex = Number(answers[question.id]);
        const correctIndex = Number(question.correctIndex);
        if (question.type === 'single' && selectedIndex === correctIndex) {
            score += 1;
        }
        return {
            id: question.id,
            question: question.question,
            options: question.options,
            selectedIndex,
            correctIndex,
            explanation: question.explanation || '',
        };
    });

    const payload = {
        title: quiz.value.title,
        slug: props.slug,
        chapter: props.chapter,
        total: quiz.value.questions.length,
        score,
        results,
    };

    const key = `quizResults:${props.slug}:${props.chapter}`;
    sessionStorage.setItem(key, JSON.stringify(payload));
    router.visit(resultsLink.value);
}

onMounted(async () => {
    let payload = await fetchJson<RawQuiz>(`/data/courses/${props.slug}/${props.chapter}/quiz.json`);
    if (!payload) {
        payload = await fetchJson<RawQuiz>(`/data/courses/${props.slug}/chapters/${props.chapter}/quiz.json`);
    }
    quiz.value = normalizeQuiz(payload);
    if (quiz.value) {
        currentIndex.value = 0;
    }
});
</script>

<template>
    <Head :title="title" />

    <div class="min-h-screen bg-background text-foreground">
        <header class="border-b border-border bg-background/80 backdrop-blur">
            <div class="mx-auto flex w-full max-w-4xl items-center justify-between px-6 py-5">
                <div>
                    <p class="text-xs uppercase tracking-[0.35em] text-muted-foreground">Quiz</p>
                    <h1 class="mt-2 text-2xl font-semibold text-foreground">{{ title }}</h1>
                </div>
                <Link
                    class="text-xs uppercase tracking-[0.35em] text-muted-foreground hover:text-foreground"
                    :href="courseLink"
                >
                    Back to course
                </Link>
            </div>
            <div v-if="quiz" class="mx-auto w-full max-w-4xl px-6 pb-5">
                <div class="h-2 rounded-full bg-muted/60">
                    <div class="h-2 rounded-full bg-foreground" :style="`width: ${progressPercent()}%`"></div>
                </div>
            </div>
        </header>

        <main class="mx-auto w-full max-w-4xl px-6 py-10">
            <div class="rounded-2xl border border-border bg-card p-6">
                <div v-if="!quiz" class="text-sm text-muted-foreground">Loading quiz...</div>

                <div v-else>
                    <div class="mb-4 text-sm text-muted-foreground">
                        Question {{ currentIndex + 1 }} of {{ quiz.questions.length }}
                    </div>
                    <form>
                        <fieldset class="space-y-3">
                            <legend class="text-lg font-semibold text-foreground">
                                {{ currentQuestion ? currentQuestion.question : '' }}
                            </legend>
                            <div class="space-y-2">
                                <label
                                    v-for="(option, optionIndex) in currentQuestion?.options || []"
                                    :key="optionIndex"
                                    class="flex cursor-pointer items-start gap-3 rounded-xl border border-border px-4 py-3 text-sm text-muted-foreground transition hover:border-foreground/30 hover:text-foreground"
                                >
                                    <input
                                        class="mt-1"
                                        type="radio"
                                        :name="currentQuestion?.id"
                                        :value="optionIndex"
                                        v-model.number="answers[currentQuestion?.id || '']"
                                    />
                                    <span>{{ option }}</span>
                                </label>
                            </div>
                        </fieldset>
                    </form>
                    <div class="mt-6 flex flex-wrap gap-3">
                        <button
                            class="rounded-full bg-foreground px-6 py-2 text-xs font-semibold uppercase tracking-[0.3em] text-background"
                            type="button"
                            @click="handleNext"
                        >
                            {{ nextLabel() }}
                        </button>
                        <Link
                            class="rounded-full border border-border px-6 py-2 text-xs font-semibold uppercase tracking-[0.3em] text-muted-foreground hover:text-foreground"
                            :href="courseLink"
                        >
                            Back to course
                        </Link>
                    </div>
                </div>
            </div>
        </main>
    </div>
</template>
