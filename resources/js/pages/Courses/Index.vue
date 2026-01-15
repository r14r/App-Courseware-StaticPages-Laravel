<script setup lang="ts">
import Icon from '@/components/Icon.vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { login } from '@/routes';
import { computed, onMounted, ref } from 'vue';

type CourseSummary = {
    slug: string;
    title: string;
    description: string;
    id: string;
};

const courses = ref<CourseSummary[]>([]);
const isLoading = ref(true);
const layout = ref<'grid' | 'list'>('grid');

const isGridLayout = computed(() => layout.value === 'grid');
const page = usePage();
const isAuthenticated = computed(() => Boolean(page.props.auth?.user));

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

function titleFromSlug(slug: string): string {
    return slug
        .replace(/^[0-9]+-/, '')
        .replace(/-/g, ' ')
        .replace(/\b\w/g, (char) => char.toUpperCase());
}

function handleImageError(event: Event, title: string): void {
    const target = event.target as HTMLImageElement | null;
    if (!target) {
        return;
    }
    target.onerror = null;
    target.src = `https://placehold.co/600x350?text=${encodeURIComponent(title)}`;
}

async function loadCourses(): Promise<void> {
    isLoading.value = true;
    const index = await fetchJson<Array<string | { slug?: string }>>('/data/courses/index.json');

    if (index) {
        console.log('Files loaded:', index);
    }

    if (!index || !Array.isArray(index)) {
        courses.value = [];
        isLoading.value = false;
        return;
    }

    const list: CourseSummary[] = [];

    for (const entry of index) {
        const slug = typeof entry === 'string' ? entry : entry.slug || '';
        if (!slug) {
            continue;
        }
        const course = await fetchJson<{ title?: string; description?: string; id?: string }>(
            `/data/courses/${encodeURIComponent(slug)}/chapters.json`,
        );
        if (!course) {
            continue;
        }
        list.push({
            slug,
            title: course.title || titleFromSlug(slug),
            description: course.description || '',
            id: course.id || slug,
        });
    }

    courses.value = list;
    console.log('Courses found:', list);
    isLoading.value = false;
}

onMounted(() => {
    loadCourses();
});
</script>

<template>
    <Head title="Course Library" />

    <div class="min-h-screen bg-background text-foreground">
        <header class="border-b border-border bg-background/80 backdrop-blur">
            <div class="mx-auto flex w-full max-w-6xl items-center justify-between px-6 py-4">
                <Link href="/" class="text-sm font-semibold uppercase tracking-[0.35em] text-foreground">
                    Courseware
                </Link>
                <div class="flex items-center gap-6">
                    <nav class="flex items-center gap-4 text-xs uppercase tracking-[0.35em] text-muted-foreground">
                        <Link href="/" class="transition hover:text-foreground">Courses</Link>
                        <Link v-if="isAuthenticated" href="/dashboard" class="transition hover:text-foreground">
                            Dashboard
                        </Link>
                    </nav>
                    <Link
                        v-if="!isAuthenticated"
                        :href="login()"
                        class="rounded-full border border-border px-4 py-2 text-xs font-semibold uppercase tracking-[0.35em] text-foreground transition hover:bg-muted"
                    >
                        Login
                    </Link>
                    <!--
                    <span class="text-xs uppercase tracking-[0.35em] text-muted-foreground">Library</span>
                    -->
                </div>
            </div>
        </header>

        <main class="mx-auto w-full max-w-6xl px-6 py-12">
            <section class="mb-10">
                <p class="text-xs uppercase tracking-[0.4em] text-muted-foreground">Course Catalog</p>
                <div class="mt-3 flex flex-wrap items-end justify-between gap-6">
                    <div>
                        <h1 class="text-4xl font-semibold tracking-tight text-foreground md:text-5xl">
                            Pick a course and dive in.
                        </h1>
                        <p class="mt-3 max-w-2xl text-sm text-muted-foreground">
                            Explore the catalog and open any course to start learning.
                        </p>
                    </div>
                    <div class="flex flex-col items-end gap-3">
                        <div class="rounded-full border border-border bg-card px-4 py-2 text-xs text-muted-foreground">
                            {{ courses.length }} Courses
                        </div>
                        <div class="flex items-center gap-2 rounded-full border border-border bg-card p-1">
                            <button
                                type="button"
                                class="rounded-full p-2 transition"
                                :class="
                                    isGridLayout
                                        ? 'bg-foreground text-background'
                                        : 'text-muted-foreground hover:bg-muted hover:text-foreground'
                                "
                                aria-label="Show courses as grid"
                                :aria-pressed="isGridLayout"
                                @click="layout = 'grid'"
                            >
                                <Icon name="layoutGrid" class="h-4 w-4" />
                            </button>
                            <button
                                type="button"
                                class="rounded-full p-2 transition"
                                :class="
                                    !isGridLayout
                                        ? 'bg-foreground text-background'
                                        : 'text-muted-foreground hover:bg-muted hover:text-foreground'
                                "
                                aria-label="Show courses as list"
                                :aria-pressed="!isGridLayout"
                                @click="layout = 'list'"
                            >
                                <Icon name="list" class="h-4 w-4" />
                            </button>
                        </div>
                    </div>
                </div>
            </section>

            <section
                v-if="isLoading"
                :class="
                    isGridLayout
                        ? 'grid gap-6 sm:grid-cols-2 lg:grid-cols-3'
                        : 'flex flex-col gap-4'
                "
            >
                <div
                    v-for="n in 6"
                    :key="n"
                    class="animate-pulse rounded-2xl bg-muted"
                    :class="isGridLayout ? 'h-64' : 'h-24'"
                ></div>
            </section>

            <section
                v-else
                :class="
                    isGridLayout
                        ? 'grid gap-6 sm:grid-cols-2 lg:grid-cols-3'
                        : 'flex flex-col gap-4'
                "
            >
                <component
                    v-for="course in courses"
                    :key="course.slug"
                    :is="isAuthenticated ? Link : 'div'"
                    :href="isAuthenticated ? `/courses/${encodeURIComponent(course.slug)}` : undefined"
                    :aria-disabled="!isAuthenticated"
                    :tabindex="isAuthenticated ? 0 : -1"
                    class="group overflow-hidden rounded-2xl border border-border bg-card shadow-sm transition hover:shadow-lg"
                    :class="
                        isGridLayout
                            ? [
                                'flex flex-col',
                                isAuthenticated ? 'hover:-translate-y-1' : 'cursor-default pointer-events-none',
                            ]
                            : [
                                'flex flex-col sm:flex-row',
                                isAuthenticated ? '' : 'cursor-default pointer-events-none',
                            ]
                    "
                >
                    <div class="relative" :class="isGridLayout ? '' : 'sm:w-56 sm:shrink-0'">
                        <img
                            class="w-full object-cover"
                            :class="isGridLayout ? 'h-40' : 'h-40 sm:h-full'"
                            :src="`/assets/${course.slug}.png`"
                            :alt="course.title"
                            @error="handleImageError($event, course.title)"
                        />
                        <div class="absolute inset-0 bg-gradient-to-t from-background/80 via-transparent"></div>
                    </div>
                    <div class="flex flex-1 flex-col gap-4 p-5">
                        <div class="space-y-2">
                            <h2 class="text-lg font-semibold text-foreground">
                                {{ course.title }}
                            </h2>
                            <p class="text-sm text-muted-foreground">
                                {{ course.description }}
                            </p>
                        </div>
                        <div class="mt-auto flex items-center justify-between text-xs text-muted-foreground">
                            <span>ID: {{ course.id }}</span>
                            <span
                                class="rounded-full border border-border bg-muted px-3 py-1 text-xs uppercase tracking-[0.3em] text-foreground transition"
                                :class="
                                    isAuthenticated
                                        ? 'group-hover:bg-foreground group-hover:text-background'
                                        : 'opacity-70'
                                "
                            >
                                {{ isAuthenticated ? 'Open' : 'Login to access' }}
                            </span>
                        </div>
                    </div>
                </component>
            </section>
        </main>
    </div>
</template>
