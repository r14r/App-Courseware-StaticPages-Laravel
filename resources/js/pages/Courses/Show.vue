<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, nextTick, onMounted, ref } from 'vue';

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
let prism: typeof import('prismjs') | null = null;

const currentChapter = computed(() => chapters.value[selectedChapterIndex.value]);
const quizLink = computed(() => {
    if (!currentChapter.value) {
        return '';
    }
    return `/courses/${encodeURIComponent(props.slug)}/chapters/${encodeURIComponent(currentChapter.value.id)}/quiz`;
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

async function ensurePrism(): Promise<void> {
    if (prism || typeof window === 'undefined') {
        return;
    }

    const module = await import('prismjs');
    await import('prismjs/components/prism-bash');
    await import('prismjs/components/prism-json');
    prism = module.default ?? module;
}

function highlightCode(): void {
    if (!prism) {
        return;
    }
    prism.highlightAll();
}

async function updateContent(html: string, title: string, onlyTopic: boolean): Promise<void> {
    chapterContentHtml.value = html;
    chapterTitle.value = title;
    showOnlyTopic.value = onlyTopic;
    await nextTick();
    await ensurePrism();
    highlightCode();
}

function titleFromFile(file: string): string {
    return file
        .replace(/^\d+-/, '')
        .replace(/\.json$/g, '')
        .replace(/-/g, ' ')
        .replace(/\b\w/g, (char) => char.toUpperCase());
}

function normalizeTopics(entries: Array<string | { file?: string; title?: string }>): TopicEntry[] {
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
            const quizEntry = normalizedTopics.find((topic) => topic.file.toLowerCase().endsWith('quiz.json'));
            chapterCopy.topics = normalizedTopics.filter((topic) => !topic.file.toLowerCase().endsWith('quiz.json'));
            if (quizEntry) {
                chapterCopy.topics.push({
                    file: quizEntry.file,
                    title: quizEntry.title || 'Quiz',
                });
            }
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
        const quizEntry = normalizedTopics.find((topic) => topic.file.toLowerCase().endsWith('quiz.json'));
        const filteredTopics = normalizedTopics.filter((topic) => !topic.file.toLowerCase().endsWith('quiz.json'));

        chapter.topics = quizEntry
            ? [...filteredTopics, { file: quizEntry.file, title: quizEntry.title || 'Quiz' }]
            : filteredTopics;

        topics.value = normalizedTopics;
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
    quizAvailable.value = Boolean(quiz && quiz.questions);
    quizTitle.value = quiz?.title || 'Quiz';
}

async function loadTopic(index: number): Promise<void> {
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

    if (filename.toLowerCase().endsWith('quiz.json')) {
        goToQuiz();
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

function nextChapter(): void {
    if (topics.value.length && selectedTopicIndex.value < topics.value.length - 1) {
        loadTopic(selectedTopicIndex.value + 1);
        return;
    }
    if (selectedChapterIndex.value < chapters.value.length - 1) {
        loadChapter(selectedChapterIndex.value + 1);
    }
}

function prevChapter(): void {
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
    if (!quizLink.value) {
        return;
    }
    router.visit(quizLink.value);
}

onMounted(async () => {
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
                    <img
                        class="h-16 w-16 rounded-2xl border border-border object-cover"
                        :src="`/assets/${props.slug}.png`"
                        :alt="course?.title || 'Course'"
                    />
                    <div>
                        <h1 class="text-xl font-semibold tracking-tight text-foreground">
                            {{ course?.title || 'Course' }}
                        </h1>
                        <p class="text-sm text-muted-foreground">
                            {{ course?.description }}
                        </p>
                    </div>
                </div>
            </div>
        </header>

        <main class="mx-auto w-full max-w-6xl px-6 py-10">
            <div class="grid gap-8 lg:grid-cols-[280px_1fr]">
                <aside>
                    <div class="rounded-2xl border border-border bg-card p-5">
                        <h2 class="text-xs uppercase tracking-[0.3em] text-muted-foreground">Chapters</h2>
                        <div class="mt-4 space-y-3">
                            <div
                                v-for="(chapter, index) in chapters"
                                :key="chapter.id"
                                class="rounded-xl border border-border bg-muted/60 p-3"
                            >
                                <button
                                    class="w-full text-left text-sm font-semibold text-foreground"
                                    @click="loadChapter(index)"
                                >
                                    {{ index + 1 }}. {{ chapter.title }}
                                </button>
                                <ul
                                    v-if="chapter.topics && chapter.topics.length && selectedChapterIndex === index"
                                    class="mt-3 space-y-2 text-sm"
                                >
                                    <li v-for="(topic, topicIndex) in chapter.topics" :key="topic.file || topic.title">
                                        <button
                                            type="button"
                                            class="w-full rounded-full px-3 py-1 text-left text-muted-foreground transition hover:text-foreground"
                                            :class="
                                                selectedChapterIndex === index && selectedTopicIndex === topicIndex
                                                    ? 'bg-muted text-foreground'
                                                    : 'text-muted-foreground'
                                            "
                                            @click="loadChapter(index).then(() => loadTopic(topicIndex))"
                                        >
                                            {{ topic.title }}
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
                                <p class="text-xs uppercase tracking-[0.35em] text-muted-foreground">Lesson</p>
                                <h2 class="mt-2 text-2xl font-semibold text-foreground">
                                    {{ chapterTitle || currentChapter?.title || '' }}
                                </h2>
                            </div>
                            <div class="text-xs uppercase tracking-[0.3em] text-muted-foreground">
                                {{ currentChapter?.id || 'Chapter' }}
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
                                <button
                                    type="button"
                                    class="rounded-full border border-border px-4 py-2 text-xs uppercase tracking-[0.3em] text-muted-foreground hover:text-foreground"
                                    @click="prevChapter"
                                >
                                    Prev
                                </button>
                                <button
                                    type="button"
                                    class="rounded-full border border-border px-4 py-2 text-xs uppercase tracking-[0.3em] text-muted-foreground hover:text-foreground"
                                    @click="nextChapter"
                                >
                                    Next
                                </button>
                            </div>
                            <button
                                v-if="quizAvailable"
                                type="button"
                                class="rounded-full bg-foreground px-4 py-2 text-xs font-semibold uppercase tracking-[0.3em] text-background"
                                @click="goToQuiz"
                            >
                                {{ quizTitle }}
                            </button>
                        </div>
                    </div>
                </section>
            </div>
        </main>
    </div>
</template>
