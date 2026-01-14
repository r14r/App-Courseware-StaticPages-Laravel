<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, nextTick, onBeforeUnmount, onMounted, ref } from 'vue';

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
    const payload = await fetchJson<Course>(`/data/courses/${props.slug}/course.json`);
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
        topics.value = normalizeTopics(topicsIndex);
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
        chapterContentHtml.value = renderContent(content);
        chapterTitle.value = chapter.title || '';
        showOnlyTopic.value = false;
        await nextTick();
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
        chapterContentHtml.value = renderContent(cached);
        chapterTitle.value = cached.title || topicEntry.title || chapter.title || '';
        showOnlyTopic.value = true;
        await nextTick();
        return;
    }

    let data = await fetchJson<TopicContent>(`/data/courses/${props.slug}/${chapter.id}/${filename}`);
    if (!data) {
        data = await fetchJson<TopicContent>(`/data/courses/${props.slug}/chapters/${chapter.id}/${filename}`);
    }

    if (data) {
        topicCache.value[filename] = data;
        chapterContentHtml.value = renderContent(data);
        chapterTitle.value = data.title || topicEntry.title || chapter.title || '';
        showOnlyTopic.value = true;
        await nextTick();
        return;
    }

    chapterContentHtml.value = '<p>No content.</p>';
    chapterTitle.value = chapter.title || '';
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

function setBodyClass(isActive: boolean): void {
    const className = 'courseware-body';
    if (isActive) {
        document.body.classList.add(className);
    } else {
        document.body.classList.remove(className);
    }
}

function ensureBootstrapScript(): void {
    const scriptId = 'bootstrap-bundle-cdn';
    if (document.getElementById(scriptId)) {
        return;
    }
    const script = document.createElement('script');
    script.id = scriptId;
    script.src = 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js';
    script.defer = true;
    document.body.appendChild(script);
}

onMounted(async () => {
    setBodyClass(true);
    ensureBootstrapScript();
    isLoading.value = true;
    await loadCourse();
    if (chapters.value.length) {
        await loadChapter(0);
    }
    isLoading.value = false;
});

onBeforeUnmount(() => {
    setBodyClass(false);
});
</script>

<template>
    <Head :title="course?.title || 'Course'">
        <link
            rel="stylesheet"
            href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        />
        <link rel="stylesheet" href="/courseware.css" />
    </Head>

    <div>
        <header class="bg-light border-bottom">
            <div class="container py-3 d-flex align-items-center gap-3 flex-wrap">
                <Link href="/" class="small text-muted">&larr; All courses</Link>
                <div class="d-flex align-items-center gap-3 flex-wrap">
                    <img
                        class="rounded border"
                        :src="`/assets/${props.slug}.png`"
                        :alt="course?.title || 'Course'"
                        style="max-height: 100px; width: auto"
                    />
                    <div>
                        <h1 class="h5 mb-0">{{ course?.title || 'Course' }}</h1>
                        <p class="small text-muted mb-0">{{ course?.description }}</p>
                    </div>
                </div>
            </div>
        </header>

        <main class="container py-4">
            <div class="row">
                <aside class="col-md-3 mb-3">
                    <div class="card sticky-top" style="top: 1rem">
                        <div class="card-body">
                            <h2 class="h6">Chapters</h2>
                            <div class="accordion" id="chaptersAccordion">
                                <div v-for="(chapter, index) in chapters" :key="chapter.id" class="accordion-item">
                                    <h2 class="accordion-header" :id="`heading-${index}`">
                                        <button
                                            class="accordion-button collapsed"
                                            type="button"
                                            data-bs-toggle="collapse"
                                            :data-bs-target="`#collapse-${index}`"
                                            aria-expanded="false"
                                            :aria-controls="`collapse-${index}`"
                                            @click="loadChapter(index)"
                                        >
                                            <span>{{ index + 1 }}. {{ chapter.title }}</span>
                                        </button>
                                    </h2>
                                    <div
                                        :id="`collapse-${index}`"
                                        class="accordion-collapse collapse"
                                        :aria-labelledby="`heading-${index}`"
                                        data-bs-parent="#chaptersAccordion"
                                    >
                                        <div class="accordion-body p-2">
                                            <ul v-if="chapter.topics && chapter.topics.length" class="list-group list-group-flush">
                                                <li
                                                    v-for="(topic, topicIndex) in chapter.topics"
                                                    :key="topic.file || topic.title"
                                                    class="list-group-item py-1"
                                                >
                                                    <a
                                                        href="#"
                                                        class="text-decoration-none"
                                                        :class="
                                                            selectedChapterIndex === index && selectedTopicIndex === topicIndex
                                                                ? 'link-primary fw-semibold'
                                                                : 'link-dark'
                                                        "
                                                        :aria-current="
                                                            selectedChapterIndex === index && selectedTopicIndex === topicIndex
                                                                ? 'true'
                                                                : 'false'
                                                        "
                                                        @click.prevent="loadChapter(index).then(() => loadTopic(topicIndex))"
                                                    >
                                                        {{ topic.title }}
                                                    </a>
                                                </li>
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </aside>

                <section class="col-md-9">
                    <div class="card">
                        <div class="card-body">
                            <h1 class="h1">{{ chapterTitle || currentChapter?.title || '' }}</h1>
                        </div>
                    </div>
                    <div class="mt-3"></div>

                    <div class="card">
                        <div class="card-body">
                            <article>
                                <div v-show="showOnlyTopic">
                                    <div class="d-flex justify-content-between align-items-start mb-2"></div>
                                    <div v-html="chapterContentHtml" class="mb-3"></div>
                                </div>
                                <div v-show="!showOnlyTopic">
                                    <div v-html="chapterContentHtml" class="mb-3"></div>
                                </div>
                            </article>

                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <div>
                                    <button class="btn btn-outline-secondary btn-sm me-2" @click="prevChapter">
                                        &larr; Prev
                                    </button>
                                    <button class="btn btn-outline-secondary btn-sm" @click="nextChapter">
                                        Next &rarr;
                                    </button>
                                </div>
                                <button v-if="quizAvailable" class="btn btn-primary btn-sm" @click="goToQuiz">
                                    {{ quizTitle }}
                                </button>
                            </div>
                        </div>
                    </div>
                </section>
            </div>
        </main>
    </div>
</template>
