/**
 * アクセス解析の日別の推移のグラフ(PV・UU の折れ線)。
 * data-role="page-view-chart" の要素の data-daily(PageViewStatsService::daily() の JSON)から SVG を描き、
 * 幅が変わったら描き直す。ポインターの位置(キーボードでは ←→)に近い日付に縦線を合わせ、その日の PV・UU をツールチップに出す。
 * 同じ値は表(<details> の中)でも見られる。
 */
const SVG_NS = 'http://www.w3.org/2000/svg';
const HEIGHT = 260;
const MARGIN = { top: 16, right: 72, bottom: 28, left: 44 };

// 色はパーシャルの凡例と同じ(resources/css/admin.css の .page-view-chart の変数)
const SERIES = [
    { key: 'views', label: 'PV', color: 'var(--chart-series-1)' },
    { key: 'unique_visitors', label: 'UU', color: 'var(--chart-series-2)' },
];

export function initPageViewCharts() {
    document.querySelectorAll('[data-role="page-view-chart"]').forEach((container) => {
        const daily = JSON.parse(container.dataset.daily || '[]');

        if (daily.length === 0) {
            return;
        }

        const plot = container.querySelector('[data-role="page-view-chart-plot"]');
        const tooltip = container.querySelector('[data-role="page-view-chart-tooltip"]');
        let width = 0;

        const resize = () => {
            if (plot.clientWidth !== width) {
                width = plot.clientWidth;
                render(plot, tooltip, daily, width);
            }
        };

        new ResizeObserver(resize).observe(plot);
        resize();
    });
}

function svgElement(name, attributes = {}) {
    const element = document.createElementNS(SVG_NS, name);
    Object.entries(attributes).forEach(([key, value]) => element.setAttribute(key, value));

    return element;
}

/**
 * 0 から最大値までを 4 つ前後に区切る、切りのよい目盛り(1・2・5 × 10 のべき乗の間隔)。
 */
function niceTicks(max) {
    if (max <= 0) {
        return [0, 1];
    }

    const rough = max / 4;
    const power = 10 ** Math.floor(Math.log10(rough));
    const step = [1, 2, 5, 10].map((factor) => factor * power).find((candidate) => candidate >= rough);
    const ticks = [];

    for (let value = 0; value < max + step; value += step) {
        ticks.push(value);
    }

    return ticks;
}

function formatDate(date, options) {
    return new Intl.DateTimeFormat(document.documentElement.lang || 'ja', { timeZone: 'UTC', ...options })
        .format(new Date(`${date}T00:00:00Z`));
}

function render(plot, tooltip, daily, width) {
    plot.replaceChildren();

    const innerWidth = Math.max(width - MARGIN.left - MARGIN.right, 1);
    const innerHeight = HEIGHT - MARGIN.top - MARGIN.bottom;
    const ticks = niceTicks(Math.max(...daily.map((day) => day.views)));
    const yMax = ticks[ticks.length - 1];
    const x = (index) => MARGIN.left + (daily.length === 1 ? innerWidth / 2 : (index / (daily.length - 1)) * innerWidth);
    const y = (value) => MARGIN.top + innerHeight - (value / yMax) * innerHeight;

    const svg = svgElement('svg', {
        width,
        height: HEIGHT,
        viewBox: `0 0 ${width} ${HEIGHT}`,
        class: 'page-view-chart-svg',
        role: 'img',
        'aria-label': plot.dataset.label || '',
        tabindex: '0',
    });

    // 横の目盛り線と縦軸の値(控えめな色の細線)
    ticks.forEach((tick) => {
        svg.append(svgElement('line', { x1: MARGIN.left, x2: MARGIN.left + innerWidth, y1: y(tick), y2: y(tick), class: tick === 0 ? 'chart-axis' : 'chart-grid' }));
        const label = svgElement('text', { x: MARGIN.left - 8, y: y(tick), class: 'chart-tick', 'text-anchor': 'end', 'dominant-baseline': 'middle' });
        label.textContent = tick.toLocaleString();
        svg.append(label);
    });

    // 横軸の日付(幅に収まる間隔で間引き、最後の日は必ず出す)
    const labelEvery = Math.max(1, Math.ceil(daily.length / Math.max(1, Math.floor(innerWidth / 64))));
    daily.forEach((day, index) => {
        const isLast = index === daily.length - 1;

        if ((daily.length - 1 - index) % labelEvery !== 0 && !isLast) {
            return;
        }

        const label = svgElement('text', { x: x(index), y: HEIGHT - 8, class: 'chart-tick', 'text-anchor': isLast ? 'end' : 'middle' });
        label.textContent = formatDate(day.date, { month: 'numeric', day: 'numeric' });
        svg.append(label);
    });

    // 折れ線と、右端の直接ラベル(値)。2 本のラベルが重なるときは上下に少しずらす
    const endLabels = SERIES.map((series) => {
        const points = daily.map((day, index) => `${x(index)},${y(day[series.key])}`).join(' ');
        svg.append(svgElement('polyline', { points, fill: 'none', stroke: series.color, class: 'chart-line' }));

        return { series, value: daily[daily.length - 1][series.key], y: y(daily[daily.length - 1][series.key]) };
    });

    if (Math.abs(endLabels[0].y - endLabels[1].y) < 16) {
        const [upper, lower] = [...endLabels].sort((a, b) => a.y - b.y);
        const middle = (upper.y + lower.y) / 2;
        upper.y = middle - 8;
        lower.y = middle + 8;
    }

    endLabels.forEach(({ series, value, y: labelY }) => {
        const label = svgElement('text', { x: MARGIN.left + innerWidth + 8, y: labelY, class: 'chart-end-label', 'dominant-baseline': 'middle' });
        label.textContent = `${series.label} ${value.toLocaleString()}`;
        svg.append(label);
    });

    // ホバー: 近い日付に縦線と、各系列の点を合わせる
    const crosshair = svgElement('line', { y1: MARGIN.top, y2: MARGIN.top + innerHeight, class: 'chart-crosshair', visibility: 'hidden' });
    const markers = SERIES.map((series) => svgElement('circle', { r: 4, fill: series.color, class: 'chart-marker', visibility: 'hidden' }));
    svg.append(crosshair, ...markers);

    let activeIndex = null;

    const show = (index) => {
        activeIndex = index;
        const day = daily[index];

        crosshair.setAttribute('x1', x(index));
        crosshair.setAttribute('x2', x(index));
        crosshair.setAttribute('visibility', 'visible');
        markers.forEach((marker, seriesIndex) => {
            marker.setAttribute('cx', x(index));
            marker.setAttribute('cy', y(day[SERIES[seriesIndex].key]));
            marker.setAttribute('visibility', 'visible');
        });

        const title = document.createElement('div');
        title.className = 'page-view-chart-tooltip-title';
        title.textContent = formatDate(day.date, { month: 'numeric', day: 'numeric', weekday: 'short' });
        const rows = SERIES.map((series) => {
            const row = document.createElement('div');
            row.className = 'page-view-chart-tooltip-row';
            const key = document.createElement('span');
            key.className = 'page-view-chart-key';
            key.style.background = series.color;
            const value = document.createElement('strong');
            value.textContent = day[series.key].toLocaleString();
            const name = document.createElement('span');
            name.className = 'text-muted';
            name.textContent = series.label;
            row.append(key, value, name);

            return row;
        });
        tooltip.replaceChildren(title, ...rows);
        tooltip.hidden = false;

        // 縦線の横に出し、右端で切れるときは左側に出す
        const left = x(index) + 12;
        tooltip.style.left = `${left + tooltip.offsetWidth > width ? x(index) - 12 - tooltip.offsetWidth : left}px`;
        tooltip.style.top = `${MARGIN.top}px`;
    };

    const hide = () => {
        activeIndex = null;
        crosshair.setAttribute('visibility', 'hidden');
        markers.forEach((marker) => marker.setAttribute('visibility', 'hidden'));
        tooltip.hidden = true;
    };

    const nearestIndex = (clientX) => {
        const relative = clientX - svg.getBoundingClientRect().left - MARGIN.left;
        const ratio = daily.length === 1 ? 0 : relative / innerWidth;

        return Math.min(daily.length - 1, Math.max(0, Math.round(ratio * (daily.length - 1))));
    };

    svg.addEventListener('pointermove', (event) => show(nearestIndex(event.clientX)));
    svg.addEventListener('pointerleave', hide);
    svg.addEventListener('focus', () => show(activeIndex ?? daily.length - 1));
    svg.addEventListener('blur', hide);
    svg.addEventListener('keydown', (event) => {
        const moves = { ArrowLeft: -1, ArrowRight: 1 };

        if (event.key in moves) {
            event.preventDefault();
            show(Math.min(daily.length - 1, Math.max(0, (activeIndex ?? daily.length - 1) + moves[event.key])));
        } else if (event.key === 'Escape') {
            hide();
        }
    });

    plot.append(svg);
}
