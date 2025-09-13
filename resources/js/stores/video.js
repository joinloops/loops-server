import { defineStore } from "pinia";
import axios from "~/plugins/axios";

export const useVideoStore = defineStore("video", {
    state: () => ({
        currentVideo: null,
        error: null,
    }),

    actions: {
        async setVideo(data) {
            this.currentVideo = data;
        },

        async getVideoById(id) {
            const axiosInstance = axios.getAxiosInstance();
            try {
                const res = await axiosInstance.get(`/api/v1/video/${id}`);
                this.currentVideo = res.data.data;
            } catch (error) {
                console.error("Error fetching video:", error);
                throw error;
            }
        },

        async deleteVideoById(id) {
            const axiosInstance = axios.getAxiosInstance();
            try {
                const res = await axiosInstance.post(
                    `/api/v1/video/delete/${id}`,
                );
                this.currentVideo = null;
            } catch (error) {
                console.error("Error fetching video:", error);
                throw error;
            }
        },

        async updateVideoStore({
            caption,
            can_download,
            can_comment,
            is_pinned,
        }) {
            const axiosInstance = axios.getAxiosInstance();
            try {
                const res = await axiosInstance
                    .post("/api/v1/video/edit/" + this.currentVideo.id, {
                        caption: caption,
                        is_pinned: is_pinned,
                        can_download: can_download,
                        can_comment: can_comment,
                    })
                    .then((res) => {
                        this.currentVideo = res.data;
                    });
            } catch (error) {
                console.error('Error fetching video:', error);
            }
        },

        async decrementCommentCount() {
            this.currentVideo = {
                ...this.currentVideo,
                comments: Math.max((this.currentVideo.comments || 0) - 1, 0),
            };
        },

        async incrementCommentCount() {
            this.currentVideo = {
                ...this.currentVideo,
                comments: (this.currentVideo.comments || 0) + 1,
            };
        },

        async likeVideo(videoId) {
            const axiosInstance = axios.getAxiosInstance();

            try {
                const uri = `/api/v1/video/like/${videoId}`;
                return await axiosInstance.post(uri).then((res) => {
                    this.currentVideo.has_liked = res.data.has_liked;
                    this.currentVideo.likes = res.data.likes;
                    return res;
                });
            } catch (error) {
                console.error("Error liking post:", error);
                throw error;
            }
        },

        async unlikeVideo(videoId) {
            const axiosInstance = axios.getAxiosInstance();

            try {
                const uri = `/api/v1/video/unlike/${videoId}`;
                return await axiosInstance.post(uri).then((res) => {
                    this.currentVideo.has_liked = res.data.has_liked;
                    this.currentVideo.likes = res.data.likes;
                    return res;
                });
            } catch (error) {
                console.error("Error liking post:", error);
                throw error;
            }
        },
    },

    getters: {
        video: (state) => state.currentVideo,
    },
});
