export default function siteBanner(version) {
    const storageKey = `waymark.site-banner.${version}`;

    return {
        visible: true,

        init() {
            try {
                this.visible = window.localStorage.getItem(storageKey) !== 'dismissed';
            } catch {
                this.visible = true;
            }
        },

        dismiss() {
            this.visible = false;

            try {
                window.localStorage.setItem(storageKey, 'dismissed');
            } catch {
                // The dismissal remains effective for this page view.
            }
        },
    };
}
