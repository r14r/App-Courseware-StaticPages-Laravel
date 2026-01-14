<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { onBeforeUnmount, onMounted, ref } from 'vue';

type CourseSummary = {
    slug: string;
    title: string;
    description: string;
    id: string;
};

const courses = ref<CourseSummary[]>([]);
const isLoading = ref(true);

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
            `/data/courses/${slug}/course.json`,
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
    loadCourses();
});

onBeforeUnmount(() => {
    setBodyClass(false);
});
</script>

<template>
    <Head title="Course Learning Platform">
        <link
            rel="stylesheet"
            href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        />
        <link
            href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600;700&family=Newsreader:opsz,wght@6..72,400;6..72,500&display=swap"
            rel="stylesheet"
        />
        <link rel="stylesheet" href="/courseware.css" />
    </Head>

    <div class="bg-body-tertiary">
        <header>
            <div class="navbar navbar-dark bg-dark shadow-sm">
                <div class="container">
                    <Link href="/" class="navbar-brand d-flex align-items-center">
                        <strong>Courseware</strong>
                    </Link>
                </div>
            </div>
        </header>

        <main>
            <section class="py-5 text-center container">
                <div class="row py-lg-5">
                    <div class="col-lg-6 col-md-8 mx-auto">
                        <h1 class="fw-light">Course Library</h1>
                        <p class="lead text-body-secondary">
                            Explore the catalog and open any course to start learning.
                        </p>
                    </div>
                </div>
            </section>

            <div class="album py-5 bg-body-tertiary">
                <div class="container">
                    <div v-if="isLoading" class="text-center text-body-secondary">Loading courses...</div>
                    <div v-else class="row row-cols-1 row-cols-sm-2 row-cols-md-3 g-3">
                        <div v-for="course in courses" :key="course.id" class="col">
                            <div class="card shadow-sm h-100 album-card">
                                <img class="card-img-top" :src="`/assets/${course.slug}.png`" alt="" />
                                <div class="card-body d-flex flex-column album-card__body">
                                    <h5 class="card-title">
                                        <Link
                                            class="stretched-link text-decoration-none text-body"
                                            :href="`/courses/${encodeURIComponent(course.slug)}`"
                                        >
                                            {{ course.title }}
                                        </Link>
                                    </h5>
                                    <p class="card-text text-body-secondary">
                                        {{ course.description }}
                                    </p>
                                    <div class="d-flex justify-content-between align-items-center mt-auto">
                                        <div class="btn-group">
                                            <span class="btn btn-sm btn-outline-secondary" aria-hidden="true">
                                                Open
                                            </span>
                                        </div>
                                        <small class="text-body-secondary">ID: {{ course.id }}</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</template>
