import { defineStore } from "pinia";
import axios from "~/plugins/axios";
import { useAuthStore } from "@/stores/auth";

export const useCommentStore = defineStore("comment", {
    state: () => ({
        commentsMap: new Map(),
        cursorsMap: new Map(),
        loadingStates: new Map(),
        hasMoreMap: new Map(),
        replyCursorsMap: new Map(),
        replyLoadingStates: new Map(),
        replyHasMoreMap: new Map(),
        repliesLoadedMap: new Map(),
        likeLoadingStates: new Map(),
        pendingPosts: new Map(),
        lastSubmittedKeyAt: new Map(),
        cooldownMs: 1500,
    }),

    getters: {
        getComments() {
            return (videoId) => this.commentsMap.get(videoId) || [];
        },

        isLoading() {
            return (videoId) => this.loadingStates.get(videoId) || false;
        },

        hasMore() {
            return (videoId) => this.hasMoreMap.get(videoId) || false;
        },

        // New getters for reply pagination
        isLoadingReplies() {
            return (videoId, parentId) => {
                const key = `${videoId}-${parentId}`;
                return this.replyLoadingStates.get(key) || false;
            };
        },

        hasMoreReplies() {
            return (videoId, parentId) => {
                const key = `${videoId}-${parentId}`;
                return this.replyHasMoreMap.get(key) || false;
            };
        },

        getReplies() {
            return (videoId, parentId) => {
                const comments = this.commentsMap.get(videoId) || [];
                const parentComment = this.findCommentById(comments, parentId);
                return parentComment?.children || [];
            };
        },

        areRepliesLoaded() {
            return (videoId, parentId) => {
                const key = `${videoId}-${parentId}`;
                return this.repliesLoadedMap.get(key) || false;
            };
        },

        // New getter for like loading state
        isLikeLoading() {
            return (videoId, commentId) => {
                const key = `${videoId}-${commentId}`;
                return this.likeLoadingStates.get(key) || false;
            };
        },
    },

    actions: {
        _unshiftComment(videoId, comment) {
            const list = this.commentsMap.get(videoId) || [];
            this.commentsMap.set(videoId, [comment, ...list]);
        },

        _replaceTemp(videoId, tempId, serverComment) {
            const list = this.commentsMap.get(videoId) || [];
            const next = list.map((c) => (c.id === tempId ? serverComment : c));
            this.commentsMap.set(videoId, next);
        },

        _removeById(videoId, id) {
            const list = this.commentsMap.get(videoId) || [];
            this.commentsMap.set(
                videoId,
                list.filter((c) => c.id !== id),
            );
        },

        _makeKey(videoId, text) {
            return `${videoId}:${text}`;
        },

        findCommentById(comments, commentId) {
            if (!Array.isArray(comments)) return null;

            for (const comment of comments) {
                if (comment.id.toString() === commentId.toString()) {
                    return comment;
                }

                if (comment.children) {
                    const found = this.findCommentById(
                        comment.children,
                        commentId,
                    );
                    if (found) return found;
                }
            }

            return null;
        },

        findAndUpdateComment(comments, parentId, updateFn) {
            // Ensure comments is an array
            if (!Array.isArray(comments)) {
                console.warn("Comments is not an array:", comments);
                return [];
            }

            return comments.map((comment) => {
                if (comment.id.toString() === parentId.toString()) {
                    const updatedComment = updateFn(comment);
                    if (!updatedComment.children) {
                        updatedComment.children = [];
                    }
                    return updatedComment;
                }

                if (comment.children) {
                    return {
                        ...comment,
                        children: this.findAndUpdateComment(
                            comment.children,
                            parentId,
                            updateFn,
                        ),
                    };
                }

                return comment;
            });
        },

        findAndRemoveComment(comments, commentId) {
            if (!Array.isArray(comments)) return [];

            return comments.filter((comment) => {
                if (comment.id.toString() === commentId.toString()) {
                    return false;
                }

                if (comment.children) {
                    comment.children = this.findAndRemoveComment(
                        comment.children,
                        commentId,
                    );
                }

                return true;
            });
        },

        async fetchComments(videoId, reset = false) {
            const replyKeys = Array.from(this.replyLoadingStates.keys()).filter(
                (key) =>
                    key.startsWith(`${videoId}-`) &&
                    this.replyLoadingStates.get(key),
            );

            if (replyKeys.length > 0) {
                console.log(
                    "Skipping main comments fetch - replies are loading",
                );
                return;
            }

            if (this.loadingStates.get(videoId)) return;
            if (!this.hasMoreMap.get(videoId) && !reset) return;

            try {
                this.loadingStates.set(videoId, true);
                const axiosInstance = axios.getAxiosInstance();
                const cursor = reset ? null : this.cursorsMap.get(videoId);
                const limit = 10;

                const response = await axiosInstance.get(
                    `/api/v1/video/comments/${videoId}?cursor=${cursor}&limit=${limit}`,
                );
                const { data, meta } = response.data;

                if (reset) {
                    this.commentsMap.set(videoId, data);
                } else {
                    const existingComments =
                        this.commentsMap.get(videoId) || [];
                    this.commentsMap.set(videoId, [
                        ...existingComments,
                        ...data,
                    ]);
                }

                this.cursorsMap.set(videoId, meta.next_cursor);
                this.hasMoreMap.set(videoId, meta.next_cursor);
            } catch (error) {
                console.error("Error fetching comments:", error);
            } finally {
                this.loadingStates.set(videoId, false);
            }
        },

        async fetchReplies(videoId, parentId, reset = false) {
            const key = `${videoId}-${parentId}`;

            if (this.replyLoadingStates.get(key)) return;

            if (reset) {
                this.replyHasMoreMap.set(key, true);
            } else if (!this.replyHasMoreMap.get(key)) {
                return;
            }

            try {
                this.replyLoadingStates.set(key, true);
                const axiosInstance = axios.getAxiosInstance();
                const cursor = reset ? null : this.replyCursorsMap.get(key);
                const limit = 5;

                let url = `/api/v1/video/comments/${videoId}/replies?cr=${parentId}&limit=${limit}`;
                if (cursor) {
                    url += `&cursor=${cursor}`;
                }

                const response = await axiosInstance.get(url);
                const { data, meta } = response.data;

                const existingComments = this.commentsMap.get(videoId) || [];
                const commentIndex = existingComments.findIndex(
                    (c) => c.id.toString() === parentId.toString(),
                );

                if (commentIndex !== -1) {
                    const updatedComments = [...existingComments];
                    const updatedComment = { ...updatedComments[commentIndex] };

                    if (reset) {
                        updatedComment.children = [...data];
                    } else {
                        updatedComment.children = [
                            ...(updatedComment.children || []),
                            ...data,
                        ];
                    }

                    updatedComments[commentIndex] = updatedComment;

                    this.commentsMap.set(videoId, updatedComments);
                }

                this.replyCursorsMap.set(key, meta.next_cursor);
                this.replyHasMoreMap.set(key, !!meta.next_cursor);
                this.repliesLoadedMap.set(key, true);
            } catch (error) {
                console.error("Error fetching replies:", error);
                throw error;
            } finally {
                this.replyLoadingStates.set(key, false);
            }
        },

        async addComment(videoId, rawContent) {
            const content = String(rawContent ?? "").trim();
            if (!content) return null;

            const key = this._makeKey(videoId, content);
            const now = Date.now();

            const lastAt = this.lastSubmittedKeyAt.get(key);

            if (lastAt && now - lastAt < this.cooldownMs) {
                return null;
            }

            if (this.pendingPosts.has(key)) {
                return this.pendingPosts.get(key);
            }

            const authStore = useAuthStore();
            const tempId = `temp-${now}-${Math.random().toString(36).slice(2)}`;
            const tempComment = {
                id: tempId,
                account: authStore.getUser,
                caption: content,
                created_at: Date.now(),
                children: [],
                likes: 0,
                dislikes: 0,
                liked: false,
                pending: true,
            };

            const existing = this.getComments(videoId);
            const alreadyTemp = existing.some(
                (c) => c.pending && c.caption === content,
            );
            if (!alreadyTemp) {
                this._unshiftComment(videoId, tempComment);
            }

            const promise = (async () => {
                try {
                    const axiosInstance = axios.getAxiosInstance();
                    const res = await axiosInstance.post(
                        `/api/v1/video/comments/${String(videoId)}`,
                        { comment: content },
                        {
                            // todo: Add idempotency support
                            // headers: { 'Idempotency-Key': key },
                        },
                    );

                    const payload = res?.data?.data?.[0] ?? {};
                    const serverComment = {
                        ...payload,
                        children: [],
                        likes: payload?.likes ?? 0,
                        dislikes: payload?.dislikes ?? 0,
                        liked: payload?.liked ?? false,
                        pending: false,
                    };

                    this._replaceTemp(videoId, tempId, serverComment);

                    this.lastSubmittedKeyAt.set(key, Date.now());

                    return serverComment;
                } catch (err) {
                    this._removeById(videoId, tempId);
                    throw err;
                } finally {
                    this.pendingPosts.delete(key);
                }
            })();

            this.pendingPosts.set(key, promise);
            return promise;
        },

        async addCommentReply(videoId, parentId, content) {
            try {
                const axiosInstance = axios.getAxiosInstance();
                const res = await axiosInstance.post(
                    "/api/v1/video/comments/" + videoId.toString(),
                    {
                        parent_id: parentId.toString(),
                        comment: content,
                    },
                );

                const newComment = {
                    ...res.data.data[0],
                    children: [],
                    likes: 0,
                    replies: 0,
                    liked: false,
                    url:
                        res.data.data[0].url ||
                        `c:id:${res.data.data[0].account?.id}`,
                    is_owner: true,
                    in_reply_to: parentId.toString(),
                };

                const existingComments = this.commentsMap.get(videoId) || [];

                const updatedComments = this.findAndUpdateComment(
                    existingComments,
                    parentId,
                    (parentComment) => ({
                        ...parentComment,
                        children: [
                            newComment,
                            ...(Array.isArray(parentComment.children)
                                ? parentComment.children
                                : []),
                        ],
                    }),
                );

                this.commentsMap.set(videoId, updatedComments);
            } catch (error) {
                console.error("Error adding comment:", error);
                throw error;
            }
        },

        async deleteComment(videoId, commentId) {
            try {
                const axiosInstance = axios.getAxiosInstance();
                await axiosInstance.post(
                    `/api/v1/comments/delete/${videoId}/${commentId}`,
                );

                const existingComments = this.commentsMap.get(videoId) || [];
                const updatedComments = this.findAndRemoveComment(
                    existingComments,
                    commentId,
                );
                this.commentsMap.set(videoId, updatedComments);
            } catch (error) {
                console.error("Error deleting comment:", error);
                throw error;
            }
        },

        async deleteCommentReply(videoId, parentCommentId, commentId) {
            try {
                const axiosInstance = axios.getAxiosInstance();
                await axiosInstance.post(
                    `/api/v1/comments/delete/${videoId}/${parentCommentId}/${commentId}`,
                );

                const existingComments = this.commentsMap.get(videoId) || [];
                const updatedComments = this.findAndRemoveComment(
                    existingComments,
                    commentId,
                );
                this.commentsMap.set(videoId, updatedComments);
            } catch (error) {
                console.error("Error deleting comment reply:", error);
                throw error;
            }
        },

        async likeComment(videoId, commentId) {
            const key = `${videoId}-${commentId}`;

            if (this.likeLoadingStates.get(key)) return;

            try {
                this.likeLoadingStates.set(key, true);
                const axiosInstance = axios.getAxiosInstance();
                await axiosInstance.post(
                    `/api/v1/comments/like/${videoId}/${commentId}`,
                );

                const existingComments = this.commentsMap.get(videoId) || [];
                const updatedComments = this.findAndUpdateComment(
                    existingComments,
                    commentId,
                    (comment) => ({
                        ...comment,
                        liked: true,
                        likes: (comment.likes || 0) + 1,
                    }),
                );
                this.commentsMap.set(videoId, updatedComments);
            } catch (error) {
                console.error("Error liking comment:", error);
                throw error;
            } finally {
                this.likeLoadingStates.set(key, false);
            }
        },

        async unlikeComment(videoId, commentId) {
            const key = `${videoId}-${commentId}`;

            if (this.likeLoadingStates.get(key)) return;

            try {
                this.likeLoadingStates.set(key, true);
                const axiosInstance = axios.getAxiosInstance();
                await axiosInstance.post(
                    `/api/v1/comments/unlike/${videoId}/${commentId}`,
                );

                const existingComments = this.commentsMap.get(videoId) || [];
                const updatedComments = this.findAndUpdateComment(
                    existingComments,
                    commentId,
                    (comment) => ({
                        ...comment,
                        liked: false,
                        likes: Math.max((comment.likes || 0) - 1, 0),
                    }),
                );
                this.commentsMap.set(videoId, updatedComments);
            } catch (error) {
                console.error("Error unliking comment:", error);
                throw error;
            } finally {
                this.likeLoadingStates.set(key, false);
            }
        },

        async likeNestedComment(videoId, parentCommentId, commentId) {
            const key = `${videoId}-${commentId}`;

            if (this.likeLoadingStates.get(key)) return;

            try {
                this.likeLoadingStates.set(key, true);
                const axiosInstance = axios.getAxiosInstance();
                await axiosInstance.post(
                    `/api/v1/comments/like/${videoId}/${parentCommentId}/${commentId}`,
                );

                // Update nested comment in store
                const existingComments = this.commentsMap.get(videoId) || [];
                const updatedComments = this.findAndUpdateComment(
                    existingComments,
                    commentId,
                    (comment) => ({
                        ...comment,
                        liked: true,
                        likes: (comment.likes || 0) + 1,
                    }),
                );
                this.commentsMap.set(videoId, updatedComments);
            } catch (error) {
                console.error("Error liking nested comment:", error);
                throw error;
            } finally {
                this.likeLoadingStates.set(key, false);
            }
        },

        async unlikeNestedComment(videoId, parentCommentId, commentId) {
            const key = `${videoId}-${commentId}`;

            if (this.likeLoadingStates.get(key)) return;

            try {
                this.likeLoadingStates.set(key, true);
                const axiosInstance = axios.getAxiosInstance();
                await axiosInstance.post(
                    `/api/v1/comments/unlike/${videoId}/${parentCommentId}/${commentId}`,
                );

                const existingComments = this.commentsMap.get(videoId) || [];
                const updatedComments = this.findAndUpdateComment(
                    existingComments,
                    commentId,
                    (comment) => ({
                        ...comment,
                        liked: false,
                        likes: Math.max((comment.likes || 0) - 1, 0),
                    }),
                );
                this.commentsMap.set(videoId, updatedComments);
            } catch (error) {
                console.error("Error unliking nested comment:", error);
                throw error;
            } finally {
                this.likeLoadingStates.set(key, false);
            }
        },

        clearComments(videoId) {
            this.commentsMap.delete(videoId);
            this.cursorsMap.delete(videoId);
            this.loadingStates.delete(videoId);
            this.hasMoreMap.delete(videoId);

            const keysToDelete = [];
            for (const key of this.replyCursorsMap.keys()) {
                if (key.startsWith(`${videoId}-`)) {
                    keysToDelete.push(key);
                }
            }

            keysToDelete.forEach((key) => {
                this.replyCursorsMap.delete(key);
                this.replyLoadingStates.delete(key);
                this.replyHasMoreMap.delete(key);
                this.repliesLoadedMap.delete(key);
            });

            const likeKeysToDelete = [];
            for (const key of this.likeLoadingStates.keys()) {
                if (key.startsWith(`${videoId}-`)) {
                    likeKeysToDelete.push(key);
                }
            }
            likeKeysToDelete.forEach((key) => {
                this.likeLoadingStates.delete(key);
            });
        },

        clearReplies(videoId, parentId) {
            const key = `${videoId}-${parentId}`;
            this.replyCursorsMap.delete(key);
            this.replyLoadingStates.delete(key);
            this.replyHasMoreMap.delete(key);
            this.repliesLoadedMap.delete(key);

            const existingComments = this.commentsMap.get(videoId) || [];
            const updatedComments = this.findAndUpdateComment(
                existingComments,
                parentId,
                (parentComment) => ({
                    ...parentComment,
                    children: [],
                }),
            );
            this.commentsMap.set(videoId, updatedComments);
        },

        resetStore() {
            this.commentsMap.clear();
            this.cursorsMap.clear();
            this.loadingStates.clear();
            this.hasMoreMap.clear();
            this.replyCursorsMap.clear();
            this.replyLoadingStates.clear();
            this.replyHasMoreMap.clear();
            this.repliesLoadedMap.clear();
            this.likeLoadingStates.clear();
        },
    },
});
