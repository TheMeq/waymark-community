export default () => ({
    files: [],
    async queueFiles(event) {
        this.files.push(...Array.from(event.target.files).map((file) => ({ file, status: 'ready', error: null })));
        event.target.value = '';
    },
    async uploadAll() {
        for (const item of this.files) {
            if (item.status === 'ready' || item.status === 'failed') {
                await this.upload(item);
            }
        }
    },
    async upload(item) {
        item.status = 'uploading';
        item.error = null;

        const formData = new FormData(this.$root);
        formData.delete('photos[]');
        formData.append('photos[]', item.file);
        const batchSize = Math.min(this.files.length, Number(this.$root.dataset.maxFiles ?? 1));
        formData.set('batch_size', String(batchSize));
        if (this.files.length >= Number(this.$root.dataset.deferredBatchThreshold ?? Number.MAX_SAFE_INTEGER)) {
            formData.set('defer_processing', '1');
        }

        try {
            const response = await fetch(this.$root.action, {
                method: 'POST',
                headers: { Accept: 'application/json' },
                body: formData,
            });
            const payload = await response.json();
            const result = payload.photos?.[0];

            if (!response.ok || !['uploaded', 'processing'].includes(result?.status)) {
                throw new Error(result?.errors?.[0] ?? 'This photo could not be uploaded.');
            }

            item.status = result.status;
        } catch (error) {
            item.status = 'failed';
            item.error = error instanceof Error ? error.message : 'This photo could not be uploaded.';
        }
    },
});
