/*
 * ApexCharts, loaded only on the pages that draw a chart.
 *
 * It is by far the largest thing in the bundle (roughly half of it), and only
 * three dashboards use it, yet it was downloaded and parsed on every page,
 * the public school websites and the registration page included. Those are
 * the pages search engines and first-time visitors see, on phones and mobile
 * data, where half a megabyte of unused script is the difference between a
 * page that is ready and one that is still loading.
 *
 * The dashboards keep writing exactly what they wrote before,
 * `new ApexCharts($el, {...}).render()` and `chart.updateOptions(...)`: this
 * stands in for the class, fetches the real library the first time a chart
 * is made, and replays each call on the real chart, in order, once it exists.
 */
let library = null;

const load = () => {
    library ??= import('apexcharts').then((module) => module.default);

    return library;
};

export default class LazyApexCharts {
    constructor(element, options) {
        this.ready = load().then((ApexCharts) => new ApexCharts(element, options));
    }

    call(method, ...args) {
        this.ready = this.ready.then(async (chart) => {
            await chart[method](...args);

            return chart;
        });

        return this.ready;
    }

    render() {
        return this.call('render');
    }

    updateOptions(...args) {
        return this.call('updateOptions', ...args);
    }

    updateSeries(...args) {
        return this.call('updateSeries', ...args);
    }

    destroy() {
        return this.call('destroy');
    }
}
