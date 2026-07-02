import * as Vue from 'vue';

export function createAdminApp(rootOptions, components = {}) {
    const app = Vue.createApp(rootOptions);
    for (const [name, definition] of Object.entries(components)) {
        app.component(name, definition);
    }
    return app;
}
