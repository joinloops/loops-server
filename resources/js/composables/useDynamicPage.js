import { ref, computed, inject } from 'vue'
import { useRoute } from 'vue-router'

export function useDynamicPage() {
    const route = useRoute()
    const axios = inject('axios')

    const loading = ref(true)
    const error = ref(null)
    const pageData = ref({
        title: '',
        content: '',
        meta_description: '',
        updated_at: null,
        created_at: null,
        slug: '',
        status: 'published'
    })

    const currentSlug = computed(() => {
        if (route.params.slug) {
            return `pages/${route.params.slug}`
        }

        const pathToSlug = {
            '/about': 'about',
            '/privacy': 'privacy',
            '/terms': 'terms',
            '/community-guidelines': 'community-guidelines'
        }

        return pathToSlug[route.path] || route.path.substring(1)
    })

    const pageTitle = computed(() => {
        return pageData.value.title || formatTitle(currentSlug.value)
    })

    const loadPage = async () => {
        loading.value = true
        error.value = null

        try {
            const response = await axios.get(`/api/v1/page/content`, {
                params: { slug: currentSlug.value }
            })

            pageData.value = response.data.data
            updateSEO()
        } catch (err) {
            handleError(err)
        } finally {
            loading.value = false
        }
    }

    const handleError = (err) => {
        console.error('Failed to load page:', err)

        if (err.response?.status === 404) {
            error.value = 'Page not found'
        } else if (err.response?.status === 403) {
            error.value = 'This page is not available'
        } else {
            error.value = 'Failed to load page content'
        }
    }

    const updateSEO = () => {
        const title = pageTitle.value
        const description =
            pageData.value.meta_description || extractDescription(pageData.value.content)
    }

    const extractDescription = (content) => {
        if (!content) return ''

        const tempDiv = document.createElement('div')
        tempDiv.innerHTML = content
        const text = tempDiv.textContent || tempDiv.innerText || ''
        return text.substring(0, 160) + (text.length > 160 ? '...' : '')
    }

    const formatTitle = (slug) => {
        return slug
            .split('/')
            .pop()
            .split('-')
            .map((word) => word.charAt(0).toUpperCase() + word.slice(1))
            .join(' ')
    }

    const formatDate = (dateString) => {
        if (!dateString) return ''
        return new Date(dateString).toLocaleDateString('en-US', {
            year: 'numeric',
            month: 'long',
            day: 'numeric'
        })
    }

    const retry = () => {
        loadPage()
    }

    return {
        loading,
        error,
        pageData,

        currentSlug,
        pageTitle,

        loadPage,
        retry,
        formatDate,
        formatTitle
    }
}
