<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, reactive, ref } from 'vue';

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

function setBodyClass(isActive: boolean): void {
    const className = 'courseware-body';
    if (isActive) {
        document.body.classList.add(className);
    } else {
        document.body.classList.remove(className);
    }
}

onMounted(async () => {
    setBodyClass(true);
    let payload = await fetchJson<RawQuiz>(`/data/courses/${props.slug}/${props.chapter}/quiz.json`);
    if (!payload) {
        payload = await fetchJson<RawQuiz>(`/data/courses/${props.slug}/chapters/${props.chapter}/quiz.json`);
    }
    quiz.value = normalizeQuiz(payload);
    if (quiz.value) {
        currentIndex.value = 0;
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
            <div class="container py-3">
                <h1 class="h5 mb-2">{{ title }}</h1>
                <div v-if="quiz" class="progress" role="progressbar" :aria-valuenow="progressPercent()" aria-valuemin="0" aria-valuemax="100">
                    <div class="progress-bar" :style="`width: ${progressPercent()}%`"></div>
                </div>
            </div>
        </header>

        <main class="container py-4">
            <div class="card">
                <div class="card-body">
                    <h2 class="h6 mb-2">{{ title }}</h2>
                    <div v-show="!quiz">
                        <p class="text-muted">Loading quiz...</p>
                    </div>

                    <div v-if="quiz">
                        <div class="mb-3 text-muted small">
                            Question {{ currentIndex + 1 }} of {{ quiz.questions.length }}
                        </div>
                        <form>
                            <fieldset class="mb-3" v-show="currentQuestion">
                                <legend class="fw-medium">{{ currentQuestion ? currentQuestion.question : '' }}</legend>
                                <div>
                                    <div
                                        v-for="(option, optionIndex) in currentQuestion?.options || []"
                                        :key="optionIndex"
                                        class="form-check mb-2"
                                    >
                                        <input
                                            class="form-check-input"
                                            type="radio"
                                            :name="currentQuestion?.id"
                                            :value="optionIndex"
                                            v-model.number="answers[currentQuestion?.id || '']"
                                        />
                                        <label class="form-check-label">{{ option }}</label>
                                    </div>
                                </div>
                            </fieldset>
                        </form>
                        <div class="d-flex gap-2">
                            <button class="btn btn-primary" type="button" @click="handleNext">
                                {{ nextLabel() }}
                            </button>
                            <Link class="btn btn-outline-secondary" :href="courseLink">Back to course</Link>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</template>
