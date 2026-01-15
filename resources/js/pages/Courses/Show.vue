<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { ArrowLeft, ArrowRight, CheckCircle2, HelpCircle } from 'lucide-vue-next';
import Prism from 'prismjs';
import 'prismjs/components/prism-bash';
import 'prismjs/components/prism-json';
import { computed, nextTick, onMounted, ref } from 'vue';

import type { AppPageProps } from '@/types';
type TopicEntry = {
    file: string;
    title: string;
};

type Chapter = {
    id: string;
    title: string;
    topics?: TopicEntry[];
};

type Course = {
    id?: string;
    title?: string;
    description?: string;
    chapters?: Chapter[];
};

type TopicContent = {
    title?: string;
    content?: string[];
    contentHtml?: string;
};

type Quiz = {
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

const props = defineProps<{ slug: string }>();

const page = usePage<AppPageProps>();
const debug = (level: number, line: string): void => {
    const handler = (window as unknown as { debug?: (lvl: number, msg: string) => void }).debug;

    if (typeof window === 'undefined') {
        return;
    }


    if (typeof handler === 'function') {
        handler(level, line);
        console.log(`[DEBUG ${level}] ${line}`);

    }
};
const course = ref<Course | null>(null);
const chapters = ref<Chapter[]>([]);
const topics = ref<TopicEntry[]>([]);
const selectedChapterIndex = ref(0);
const selectedTopicIndex = ref(0);
const chapterContentHtml = ref('');
const chapterTitle = ref('');
const topicCache = ref<Record<string, TopicContent | Quiz>>({});
const quizAvailable = ref(false);
const quizTitle = ref('Quiz');
const showOnlyTopic = ref(false);
const isLoading = ref(true);
const prism = Prism;

const currentChapter = computed(() => chapters.value[selectedChapterIndex.value]);
const currentTopic = computed(() => topics.value[selectedTopicIndex.value] ?? null);
const isLastTopicInChapter = computed(() => {
    if (!topics.value.length) {
        return false;
    }
    return selectedTopicIndex.value === topics.value.length - 1;
});
const isLastChapter = computed(() => {
    if (!chapters.value.length) {
        return false;
    }
    return selectedChapterIndex.value === chapters.value.length - 1;
});
const isLastTopicOverall = computed(() => isLastTopicInChapter.value && isLastChapter.value);
const quizLink = computed(() => {
    if (!currentChapter.value) {
        return '';
    }
    return `/courses/${encodeURIComponent(props.slug)}/chapters/${encodeURIComponent(currentChapter.value.id)}/quiz`;
});

async function fetchJson<T>(path: string): Promise<T | null> {
    debug(2, `fetchJson: ${path}`);
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

function highlightCode(): void {
    debug(3, 'highlightCode');
    if (!prism) {
        return;
    }
    prism.highlightAll();
}

async function updateContent(html: string, title: string, onlyTopic: boolean): Promise<void> {
    debug(2, `updateContent: ${title}`);
    chapterContentHtml.value = html;
    chapterTitle.value = title;
    showOnlyTopic.value = onlyTopic;
    await nextTick();
    highlightCode();
}

function titleFromFile(file: string): string {
    debug(4, `titleFromFile: ${file}`);
    return file
        .replace(/^\d+-/, '')
        .replace(/\.json$/g, '')
        .replace(/-/g, ' ')
        .replace(/\b\w/g, (char) => char.toUpperCase());
}

function normalizeTopics(entries: Array<string | { file?: string; title?: string }>): TopicEntry[] {
    debug(3, 'normalizeTopics');
    return entries.map((entry) => {
        if (typeof entry === 'string') {
            return { file: entry, title: titleFromFile(entry) };
        }
        const file = entry.file || String(entry);
        const title = entry.title || titleFromFile(file);
        return { file, title };
    });
}

function renderContent(data: TopicContent | null): string {
    debug(4, 'renderContent');
    if (!data) {
        return '<p>No content.</p>';
    }
    if (Array.isArray(data.content)) {
        return data.content.filter(Boolean).join('\n\n') || '<p>No content.</p>';
    }
    if (data.contentHtml) {
        return data.contentHtml;
    }
    return '<p>No content.</p>';
}

async function loadCourse(): Promise<void> {
    debug(1, `loadCourse: ${props.slug}`);
    const payload = await fetchJson<Course>(`/data/courses/${props.slug}/chapters.json`);
    if (!payload) {
        course.value = { title: 'Course not found', description: '' };
        chapters.value = [];
        return;
    }

    course.value = payload;
    console.log('Course loaded:', payload);

    const rawChapters = payload.chapters || [];
    const normalized: Chapter[] = [];

    for (const chapter of rawChapters) {
        const chapterCopy: Chapter = { ...chapter };
        let topicsIndex = await fetchJson<Array<string | { file?: string; title?: string }>>(
            `/data/courses/${props.slug}/${chapter.id}/topics.json`,
        );
        if (!topicsIndex) {
            topicsIndex = await fetchJson<Array<string | { file?: string; title?: string }>>(
                `/data/courses/${props.slug}/chapters/${chapter.id}/topics.json`,
            );
        }

        if (topicsIndex && Array.isArray(topicsIndex)) {
            const normalizedTopics = normalizeTopics(topicsIndex);
            chapterCopy.topics = normalizedTopics.filter((topic) => !topic.file.toLowerCase().endsWith('quiz.json'));
            console.log('Topics found:', {
                chapter: chapterCopy.id,
                topics: chapterCopy.topics,
            });
        } else {
            chapterCopy.topics = [];
        }

        normalized.push(chapterCopy);
    }

    chapters.value = normalized;
}

async function loadChapter(index: number): Promise<void> {
    debug(1, `loadChapter: ${index}`);
    if (index < 0 || index >= chapters.value.length) {
        return;
    }
    selectedChapterIndex.value = index;
    const chapter = chapters.value[index];

    let topicsIndex = await fetchJson<Array<string | { file?: string; title?: string }>>(
        `/data/courses/${props.slug}/${chapter.id}/topics.json`,
    );
    if (!topicsIndex) {
        topicsIndex = await fetchJson<Array<string | { file?: string; title?: string }>>(
            `/data/courses/${props.slug}/chapters/${chapter.id}/topics.json`,
        );
    }

    if (topicsIndex && Array.isArray(topicsIndex) && topicsIndex.length) {
        const normalizedTopics = normalizeTopics(topicsIndex);
        const filteredTopics = normalizedTopics.filter((topic) => !topic.file.toLowerCase().endsWith('quiz.json'));

        chapter.topics = filteredTopics;
        topics.value = filteredTopics;
        selectedTopicIndex.value = 0;
        showOnlyTopic.value = false;
        await loadTopic(selectedTopicIndex.value);
    } else {
        topics.value = [];
        selectedTopicIndex.value = 0;
        let content = await fetchJson<TopicContent>(`/data/courses/${props.slug}/${chapter.id}/content.json`);
        if (!content) {
            content = await fetchJson<TopicContent>(`/data/courses/${props.slug}/chapters/${chapter.id}/content.json`);
        }
        await updateContent(renderContent(content), chapter.title || '', false);
    }

    let quiz = await fetchJson<Quiz>(`/data/courses/${props.slug}/${chapter.id}/quiz.json`);
    if (!quiz) {
        quiz = await fetchJson<Quiz>(`/data/courses/${props.slug}/chapters/${chapter.id}/quiz.json`);
    }
    const rawQuiz = quiz as Quiz & { quiz?: Quiz };
    const questions = quiz?.questions ?? rawQuiz.quiz?.questions ?? [];
    quizAvailable.value = questions.length > 0;
    quizTitle.value = quiz?.title || rawQuiz.quiz?.title || 'Quiz';
}

async function loadTopic(index: number): Promise<void> {
    debug(1, `loadTopic: ${index}`);
    const chapter = currentChapter.value;
    if (!chapter || index < 0 || index >= topics.value.length) {
        return;
    }
    selectedTopicIndex.value = index;
    const topicEntry = topics.value[index];
    const filename = topicEntry?.file;

    if (!filename) {
        return;
    }

    if (topicCache.value[filename]) {
        const cached = topicCache.value[filename] as TopicContent;
        await updateContent(renderContent(cached), cached.title || topicEntry.title || chapter.title || '', true);
        return;
    }

    let data = await fetchJson<TopicContent>(`/data/courses/${props.slug}/${chapter.id}/${filename}`);
    if (!data) {
        data = await fetchJson<TopicContent>(`/data/courses/${props.slug}/chapters/${chapter.id}/${filename}`);
    }

    if (data) {
        topicCache.value[filename] = data;
        await updateContent(renderContent(data), data.title || topicEntry.title || chapter.title || '', true);
        return;
    }

    await updateContent('<p>No content.</p>', chapter.title || '', false);
}

async function storeChapterCompletion(chapter: Chapter): Promise<void> {
    debug(2, `storeChapterCompletion: ${chapter.id}`);
    if (!page.props.auth?.user) {
        return;
    }

    const token = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content;
    if (!token) {
        return;
    }

    await fetch('/progress/completion', {
        method: 'post',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': token,
        },
        body: JSON.stringify({
            slug: props.slug,
            chapter_id: chapter.id,
            topics: [],
        }),
    });
}

async function storeTopicCompletion(chapter: Chapter, topic: TopicEntry): Promise<void> {
    debug(2, `storeTopicCompletion: chapter=${chapter.id} topic=${topic.file ?? ''}`);
    if (!page.props.auth?.user || !topic.file) {
        return;
    }

    const token = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content;
    if (!token) {
        return;
    }

    await fetch('/progress/completion', {
        method: 'post',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': token,
        },
        body: JSON.stringify({
            slug: props.slug,
            chapter_id: chapter.id,
            topics: [`${chapter.id}/${topic.file}`],
        }),
    });
}

async function nextChapter(): Promise<void> {
    debug(1, 'nextChapter');

    const chapter = currentChapter.value;
    const topic = currentTopic.value;

    if (chapter && topic) {
        debug(1, `nextChapter: storeTopicCompletion topic=${topic.file}`);
        await storeTopicCompletion(chapter, topic);
    }

    if (topics.value.length && selectedTopicIndex.value < topics.value.length - 1) {
        debug(1, `nextChapter: loadTopic index=${selectedTopicIndex.value + 1}`);
        await loadTopic(selectedTopicIndex.value + 1);
        return;
    }

    if (topics.value.length && selectedTopicIndex.value === topics.value.length - 1 && currentChapter.value) {
        debug(1, `nextChapter: storeChapterCompletion chapter=${currentChapter.value.id}`);
        await storeChapterCompletion(currentChapter.value);
    }

    if (selectedChapterIndex.value < chapters.value.length - 1) {
        debug(1, `nextChapter: loadChapter index=${selectedChapterIndex.value + 1}`);
        await loadChapter(selectedChapterIndex.value + 1);
    }
}

function prevChapter(): void {
    debug(1, 'prevChapter');
    if (topics.value.length && selectedTopicIndex.value > 0) {
        loadTopic(selectedTopicIndex.value - 1);
        return;
    }
    if (selectedChapterIndex.value > 0) {
        const prevIndex = selectedChapterIndex.value - 1;
        loadChapter(prevIndex);
        if (topics.value.length) {
            loadTopic(topics.value.length - 1);
        }
    }
}

function goToQuiz(): void {
    debug(1, 'goToQuiz');
    if (!quizLink.value) {
        return;
    }

    const chapter = currentChapter.value;
    const topic = currentTopic.value;

    if (chapter && topic) {
        storeTopicCompletion(chapter, topic).then(async () => {
            if (isLastTopicInChapter.value) {
                await storeChapterCompletion(chapter);
            }
            router.visit(quizLink.value);
        });
        return;
    }

    router.visit(quizLink.value);
}

async function completeCourse(): Promise<void> {
    debug(1, 'completeCourse');
    const chapter = currentChapter.value;
    const topic = currentTopic.value;

    if (chapter && topic) {
        await storeTopicCompletion(chapter, topic);
        await storeChapterCompletion(chapter);
    }

    router.visit('/dashboard');
}

onMounted(async () => {
    debug(1, 'mounted');
    isLoading.value = true;
    await loadCourse();
    if (chapters.value.length) {
        await loadChapter(0);
    }
    isLoading.value = false;
});
</script>

<template>

    <Head :title="course?.title || 'Course'" />

    <div class="min-h-screen bg-background text-foreground">
        <header class="border-b border-border bg-background/80 backdrop-blur">
            <div class="mx-auto flex w-full max-w-6xl flex-wrap items-center gap-4 px-6 py-6">
                <Link href="/" class="text-xs uppercase tracking-[0.35em] text-muted-foreground">All courses</Link>
                <div class="flex flex-1 flex-wrap items-center gap-4">
                    <img class="h-16 w-16 rounded-2xl border border-border object-cover"
                        :src="`/assets/${props.slug}.png`" :alt="course?.title || 'Course'" />
                    <div>
                        <h1 class="text-xl font-semibold tracking-tight text-foreground">
                            {{ course?.title || 'Course' }}
                        </h1>
                        <p class="text-sm text-muted-foreground">
                            {{ course?.description }}
                        </p>
                    </div>
                </div>
                <nav class="flex items-center gap-4 text-xs uppercase tracking-[0.35em] text-muted-foreground">
                    <Link href="/" class="transition hover:text-foreground">Courses</Link>
                    <Link href="/dashboard" class="transition hover:text-foreground">Dashboard</Link>
                </nav>
            </div>
        </header>

        <main class="mx-auto w-full max-w-6xl px-6 py-10">
            <div class="grid gap-8 lg:grid-cols-[280px_1fr]">
                <aside>
                    <div class="rounded-2xl border border-border bg-card p-5">
                        <h2 class="text-xs uppercase tracking-[0.3em] text-muted-foreground">Chapters</h2>
                        <div class="mt-4 space-y-3">
                            <div v-for="(chapter, index) in chapters" :key="chapter.id"
                                class="rounded-xl border border-border bg-muted/60 p-3">
                                <button class="w-full text-left text-sm font-semibold text-foreground"
                                    @click="loadChapter(index)">
                                    {{ index + 1 }}. {{ chapter.title }}
                                </button>
                                <ul v-if="chapter.topics && chapter.topics.length && selectedChapterIndex === index"
                                    class="mt-3 space-y-2 text-sm">
                                    <li v-for="(topic, topicIndex) in chapter.topics" :key="topic.file || topic.title">
                                        <button type="button" class="menu-topic-item" :class="{
                                            selected:
                                                selectedChapterIndex === index && currentTopic?.file === topic.file,
                                        }" @click="loadChapter(index).then(() => loadTopic(topicIndex))">
                                            {{ topic.title }}
                                        </button>
                                    </li>
                                    <li key="quiz">
                                        <button type="button"
                                            class="w-full rounded-full px-3 py-1 text-left text-muted-foreground transition hover:text-foreground disabled:cursor-not-allowed disabled:opacity-60"
                                            :disabled="!quizAvailable" @click="goToQuiz">
                                            Quiz
                                        </button>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </aside>

                <section class="space-y-6">
                    <div class="rounded-2xl border border-border bg-card p-6">
                        <div class="flex flex-wrap items-center justify-between gap-4">
                            <div>
                                <div class="uppercase tracking-[0.3em] text-muted-foreground">
                                    {{ currentChapter?.id || 'Chapter' }}
                                </div>
                                <h2 class="mt-2 text-2xl font-semibold text-foreground">
                                    {{ chapterTitle || currentChapter?.title || '' }}
                                </h2>
                            </div>
                            <div class="flex items-center gap-2">
                                <button type="button"
                                    class="rounded-full border border-border p-2 text-muted-foreground transition hover:text-foreground"
                                    @click="prevChapter">
                                    <ArrowLeft class="h-4 w-4" />
                                    <span class="sr-only">Prev</span>
                                </button>
                                <button v-if="!isLastTopicOverall" type="button"
                                    class="rounded-full border border-border p-2 text-muted-foreground transition hover:text-foreground"
                                    @click="nextChapter">
                                    <ArrowRight class="h-4 w-4" />
                                    <span class="sr-only">Next</span>
                                </button>
                                <button v-else type="button"
                                    class="rounded-full border border-border p-2 text-muted-foreground transition hover:text-foreground"
                                    @click="completeCourse">
                                    <CheckCircle2 class="h-4 w-4" />
                                    <span class="sr-only">Done</span>
                                </button>
                                <button v-if="quizAvailable && isLastTopicInChapter" type="button"
                                    class="rounded-full border border-border p-2 text-muted-foreground transition hover:text-foreground"
                                    @click="goToQuiz">
                                    <HelpCircle class="h-4 w-4" />
                                    <span class="sr-only">Quiz</span>
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="rounded-2xl border border-border bg-card p-6">
                        <div v-if="isLoading" class="space-y-3">
                            <div class="h-5 w-2/3 animate-pulse rounded-full bg-muted"></div>
                            <div class="h-4 w-full animate-pulse rounded-full bg-muted/70"></div>
                            <div class="h-4 w-5/6 animate-pulse rounded-full bg-muted/70"></div>
                        </div>
                        <article v-else class="course-content max-w-none" v-html="chapterContentHtml"></article>

                        <div class="mt-6 flex flex-wrap items-center justify-between gap-4">
                            <div class="flex gap-2">
                                <button type="button"
                                    class="rounded-full border border-border px-4 py-2 text-xs uppercase tracking-[0.3em] text-muted-foreground hover:text-foreground"
                                    @click="prevChapter">
                                    Prev
                                </button>
                                <button v-if="!isLastTopicOverall" type="button"
                                    class="rounded-full border border-border px-4 py-2 text-xs uppercase tracking-[0.3em] text-muted-foreground hover:text-foreground"
                                    @click="nextChapter">
                                    Next
                                </button>
                                <button v-else type="button"
                                    class="rounded-full border border-border px-4 py-2 text-xs uppercase tracking-[0.3em] text-muted-foreground hover:text-foreground"
                                    @click="completeCourse">
                                    Done
                                </button>
                                <button v-if="quizAvailable && isLastTopicInChapter" type="button"
                                    class="rounded-full border border-border px-4 py-2 text-xs uppercase tracking-[0.3em] text-muted-foreground hover:text-foreground"
                                    @click="goToQuiz">
                                    Quiz
                                </button>
                            </div>
                        </div>
                    </div>
                </section>
            </div>
        </main>
    </div>
</template>
