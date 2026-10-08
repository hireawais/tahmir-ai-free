(function ($) {
    'use strict';

    var boot = window.TahmirConversion || {};
    var state = boot.data || {};
    var app = document.getElementById('tahmir-conversion-app');
    if (!app) {
        return;
    }
    var trendMetric = 'visitors';
    var breakdownView = { source: 'source', path: 'page', activity: 'click', outcome: 'dropoff' };

    function escapeHtml(value) {
        return String(value == null ? '' : value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function number(value) {
        return new Intl.NumberFormat().format(Number(value || 0));
    }

    function visitCountText(count) { return number(count) + (Number(count) === 1 ? ' visit' : ' visits'); }

    function duration(seconds) {
        seconds = Math.max(0, Number(seconds || 0));
        if (seconds < 60) {
            return Math.round(seconds) + 's';
        }
        if (seconds < 3600) {
            return Math.floor(seconds / 60) + 'm ' + Math.round(seconds % 60) + 's';
        }
        return Math.floor(seconds / 3600) + 'h ' + Math.round((seconds % 3600) / 60) + 'm';
    }

    function dateTime(value) {
        if (!value) return '—';
        var parsed = new Date(String(value).replace(' ', 'T') + 'Z');
        if (Number.isNaN(parsed.getTime())) return String(value);
        return parsed.toLocaleString([], {
            month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit'
        });
    }

    function avatarHtml(session, className) {
        var filename = String(session.avatar || '');
        if (!/^avatar-\d{3}\.svg$/.test(filename)) return '<span class="' + className + '">◉</span>';
        return '<img class="' + className + '" src="' + escapeHtml(String(boot.avatarBase || '') + filename) + '" alt="" loading="lazy">';
    }

    function visitLocation(visit) {
        return [visit.city, visit.country_code].filter(Boolean).join(', ') || 'Location unavailable';
    }

    function toast(message, error) {
        var el = app.querySelector('[data-toast]');
        el.textContent = message || (error ? boot.i18n.error : boot.i18n.saved);
        el.classList.toggle('is-error', !!error);
        el.hidden = false;
        window.clearTimeout(toast.timer);
        toast.timer = window.setTimeout(function () { el.hidden = true; }, 4200);
    }

    function request(action, data) {
        if (boot.demo) return $.Deferred().reject({message:'Upgrade to Pro to use live analytics.'}).promise();
        return $.ajax({
            url: boot.ajaxUrl,
            method: 'POST',
            dataType: 'json',
            data: $.extend({ action: action, nonce: boot.nonce }, data || {})
        }).then(function (response) {
            if (!response || !response.success) {
                return $.Deferred().reject(response && response.data ? response.data : {}).promise();
            }
            return response.data;
        });
    }

    function showTab(name) {
        app.querySelectorAll('[data-tab]').forEach(function (button) {
            button.classList.toggle('is-active', button.getAttribute('data-tab') === name);
        });
        app.querySelectorAll('[data-panel]').forEach(function (panel) {
            panel.classList.toggle('is-active', panel.getAttribute('data-panel') === name);
        });
        if (name === 'intelligence' && typeof ensureAnalysis === 'function') ensureAnalysis();
    }

    function renderReport() {
        var report = state.report || {};
        var summary = report.summary || {};
        var revenue = state.revenue || {};
        var currencies = Object.keys(revenue.currencies || {});
        var money = currencies.length === 1 ? currencies[0] + ' ' + number((revenue.currencies[currencies[0]] || {}).net) : '—';
        var moneyNote = !revenue.enabled ? 'Enable Revenue Insights' : currencies.length > 1 ? 'See each currency in Revenue Insights' : revenue.error || 'Net paid revenue';
        var perVisitor = currencies.length === 1 ? (revenue.currencies[currencies[0]] || {}).revenue_per_tracked_visitor : null;
        var kpis = [
            ['Visitors', number(summary.visitors), 'Cookie-based people'],
            ['Visits', number(summary.sessions), number(summary.pageviews) + ' page views'],
            ['Revenue', money, moneyNote],
            ['Result rate', Number(summary.conversion_rate || 0).toFixed(1) + '%', number(summary.conversions) + ' visits completed an action'],
            ['Revenue / visitor', perVisitor == null ? '—' : currencies[0] + ' ' + number(perVisitor), perVisitor == null ? 'Shown only when traffic and payments can be compared' : 'Net revenue / tracked visitor'],
            ['Bounce rate', Number(summary.bounce_rate || 0).toFixed(1) + '%', 'One page, no measured engagement'],
            ['Time engaged', duration(summary.average_engagement_seconds), 'Average per visit']
        ];
        var metrics = ['visitors', 'visits', 'revenue', 'rate', 'revenue_per_visitor', 'bounce', 'engagement'];
        app.querySelector('[data-kpis]').innerHTML = kpis.map(function (item, index) {
            return '<button type="button" class="tc-kpi ' + (trendMetric === metrics[index] ? 'is-selected' : '') + '" data-trend-metric="' + metrics[index] + '"><i class="tc-metric-icon" aria-hidden="true">' + ['◉','↗','¤','✓','÷','↩','◷'][index] + '</i><span>' + escapeHtml(item[0]) + '</span><strong>' + escapeHtml(item[1]) + '</strong><small>' + escapeHtml(item[2]) + '</small></button>';
        }).join('');
        app.querySelector('[data-analytics-notice]').hidden = !!(state.modules || {}).conversion_analytics;
        app.querySelector('[data-report-freshness]').textContent = boot.demo ? 'Sample data · explore freely' : (report.generated_at ? 'Updated ' + dateTime(report.generated_at) + ' · reports cached for up to 2 minutes' : 'Reports cached for up to 2 minutes');
        renderTrend();

        var paths = report.journey_paths || [];
        app.querySelector('[data-journey-paths]').innerHTML = paths.length ? paths.map(function (path) {
            return '<div class="tc-path-row"><div class="tc-path-row__steps">' +
                (path.steps || []).map(function (step) {
                    return '<span title="' + escapeHtml(step) + '">' + escapeHtml(step) + '</span>';
                }).join('<i aria-hidden="true">→</i>') +
                '</div><div class="tc-path-row__metrics"><strong>' + number(path.sessions) + '</strong> recent visits' +
                (Number(path.conversions || 0) ? '<b>' + number(path.conversions) + ' converted</b>' : '') +
                '</div></div>';
        }).join('') : '<div class="tc-empty">Journeys will appear after the first visits are recorded.</div>';

        renderBreakdowns();

        var journeys = report.recent_journeys || [];
        state.sessions = [];
        journeys.forEach(function (journey) { (journey.visits || []).forEach(function (visit) {
            state.sessions.push({ visitor: journey.visitor, avatar: journey.avatar, firstSeen: journey.first_seen, lastSeen: journey.last_seen, totalVisits: journey.total_visits, visit: visit });
        }); });
        state.sessions.sort(function (a, b) { return String(b.visit.last_seen).localeCompare(String(a.visit.last_seen)); });
        app.querySelector('[data-journey-count]').textContent = number(state.sessions.length) + ' recent session threads';
        app.querySelector('[data-recent-journeys]').innerHTML = state.sessions.length ? state.sessions.map(function (session, index) {
            var visit = session.visit;
            var status = visit.status === 'active' ? 'In progress' : visit.conversions ? 'Completed action' : visit.open_form ? 'Stopped at form' : 'Ended';
            return '<div class="tc-session" data-session-index="' + index + '"><button type="button" class="tc-session-trigger" data-view-session="' + index + '">' + avatarHtml(session, 'tc-session__avatar') + '<span class="tc-session__name"><strong>' + escapeHtml(session.visitor) + '</strong><small>' + escapeHtml(visit.source_handle ? visit.source + ' via @' + visit.source_handle : visit.source || 'Direct') + ' · ' + escapeHtml(visitLocation(visit)) + ' · ' + visitCountText(session.totalVisits) + '</small><span class="tc-session__route">' + (visit.page_paths || []).slice(0, 4).map(function (path) { return '<i>' + escapeHtml(path) + '</i>'; }).join('<b>→</b>') + '</span></span><span class="tc-session__time">' + escapeHtml(dateTime(visit.started)) + '</span><span class="tc-session__status ' + (visit.open_form && visit.status === 'ended' ? 'is-friction' : '') + '">' + status + '</span><span class="tc-session__arrow">→</span></button></div>';
        }).join('') : '<div class="tc-card tc-empty">No tracked sessions in this period yet.</div>';
        app.querySelector('[data-session-preview]').innerHTML = state.sessions.length ? state.sessions.slice(0, 6).map(function (session, index) {
            var visit = session.visit;
            var outcome = visit.status === 'active' ? 'In progress' : visit.conversions ? 'Completed action' : visit.open_form ? 'Stopped at form' : 'Last seen on ' + (visit.last_page || '/');
            return '<button type="button" class="tc-preview-row" data-view-session="' + index + '">' + avatarHtml(session, 'tc-preview-avatar') + '<span><strong>' + escapeHtml(session.visitor) + '</strong><small>' + visitCountText(session.totalVisits) + ' total · ' + escapeHtml(visit.source || 'Direct') + ' · ' + escapeHtml(visitLocation(visit)) + '</small></span><span class="tc-preview-outcome">' + escapeHtml(outcome) + '</span><time>' + escapeHtml(dateTime(visit.last_seen)) + '</time><b>→</b></button>';
        }).join('') : '<div class="tc-empty">No tracked sessions in this period yet.</div>';
    }

    function renderTrend() {
        var report = state.report || {}, revenue = state.revenue || {};
        var currencies = Object.keys(revenue.currencies || {});
        var metricNames = { visitors: 'Visitors', visits: 'Visits', revenue: 'Revenue', rate: 'Result rate', revenue_per_visitor: 'Revenue / visitor', bounce: 'Bounce rate', engagement: 'Time engaged' };
        var unavailable = { revenue_per_visitor: 'Revenue per visitor is a period-wide comparison; there is no verified daily visitor-to-payment match.', bounce: 'Bounce rate is calculated across complete visits for the selected period.', engagement: 'Engagement time is calculated across complete visits for the selected period.' };
        var title = metricNames[trendMetric] || 'Visits';
        app.querySelector('[data-chart-title]').textContent = title;
        app.querySelectorAll('[data-trend-metric]').forEach(function (button) { button.classList.toggle('is-selected', button.getAttribute('data-trend-metric') === trendMetric); });
        var granularity = app.querySelector('#tc-granularity').value;
        if (trendMetric === 'visitors' && granularity !== 'daily') {
            granularity = 'daily';
            app.querySelector('[data-chart-description]').textContent = 'Unique visitors are shown daily; adding daily uniques would overcount a multi-day period.';
        } else {
            app.querySelector('[data-chart-description]').textContent = granularity.charAt(0).toUpperCase() + granularity.slice(1) + ' measured activity for this period.';
        }
        if (unavailable[trendMetric] || (trendMetric === 'revenue' && currencies.length !== 1)) {
            app.querySelector('[data-trend-chart]').innerHTML = '<div class="tc-chart-explanation"><strong>' + escapeHtml(title) + '</strong><p>' + escapeHtml(unavailable[trendMetric] || (revenue.enabled ? 'Choose one currency in Revenue Insights. Different currencies are never added together.' : 'Enable Revenue Insights to view paid revenue.')) + '</p></div>';
            app.querySelector('[data-chart-primary-label]').textContent = title;
            app.querySelector('[data-chart-secondary-label]').textContent = 'No daily series';
            return;
        }
        var today = report.end_day || new Date().toISOString().slice(0, 10);
        var start = report.start_day || (report.daily || [])[0] && report.daily[0].day || today;
        var dayMap = {};
        (report.daily || []).forEach(function (row) { dayMap[row.day] = row; });
        var moneyMap = {};
        if (currencies.length === 1) (revenue.daily || []).forEach(function (row) {
            moneyMap[row.day] = Number(((row.currencies || {})[currencies[0]] || {}).net || 0);
        });
        var grouped = {}, order = [];
        for (var cursor = new Date(start + 'T00:00:00Z'), last = new Date(today + 'T00:00:00Z'), count = 0; cursor <= last && count < 366; cursor.setUTCDate(cursor.getUTCDate() + 1), count++) {
            var day = cursor.toISOString().slice(0, 10);
            var key = day;
            if (granularity === 'weekly') {
                var monday = new Date(cursor);
                monday.setUTCDate(monday.getUTCDate() - ((monday.getUTCDay() + 6) % 7));
                key = monday.toISOString().slice(0, 10);
            } else if (granularity === 'monthly') key = day.slice(0, 7);
            if (!grouped[key]) { grouped[key] = { day: key, visits: 0, visitors: 0, impressions: 0, clicks: 0, conversions: 0, revenue: 0 }; order.push(key); }
            var row = dayMap[day] || {};
            ['visits', 'visitors', 'impressions', 'clicks', 'conversions'].forEach(function (field) { grouped[key][field] += Number(row[field] || 0); });
            grouped[key].revenue += Number(moneyMap[day] || 0);
        }
        var buckets = order.map(function (key) { return grouped[key]; });
        var primary = buckets.map(function (row) {
            return trendMetric === 'rate' ? (row.visits ? row.conversions / row.visits * 100 : 0) : Number(row[trendMetric === 'revenue' ? 'revenue' : trendMetric === 'visitors' ? 'visitors' : trendMetric === 'visits' ? 'visits' : 'impressions'] || 0);
        });
        var max = Math.max.apply(null, primary.concat([1])), min = Math.min.apply(null, primary.concat([0]));
        var unit = trendMetric === 'rate' ? '%' : trendMetric === 'revenue' ? currencies[0] + ' ' : '';
        app.querySelector('[data-chart-primary-label]').textContent = title;
        app.querySelector('[data-chart-secondary-label]').textContent = 'UTC · first recorded page of each visit';
        var left=48, right=856, top=18, bottom=230, width=(right-left)/Math.max(1,buckets.length);
        var y = function(value) { return bottom-(value-min)/(max-min)*(bottom-top); };
        var bars = buckets.map(function(row,i) {
            var label = row.day + ': ' + (trendMetric === 'revenue' ? unit : '') + number(primary[i]) + (trendMetric === 'rate' ? '%' : '') + ' ' + title;
            return '<rect tabindex="0" role="img" aria-label="'+escapeHtml(label)+'" data-chart-point="'+escapeHtml(label)+'" x="'+(left+i*width+width*.13)+'" y="'+Math.min(y(0),y(primary[i]))+'" width="'+width*.74+'" height="'+Math.max(0,Math.abs(y(primary[i])-y(0)))+'" rx="'+Math.min(14,width*.37)+'"><title>'+escapeHtml(label)+'</title></rect>';
        }).join('');
        var ticks=buckets.map(function(row,i){return i===0 || i===buckets.length-1 || i%Math.max(1,Math.ceil(buckets.length/7))===0 ? '<text x="'+(left+(i+.5)*width)+'" y="253" text-anchor="middle">'+escapeHtml(row.day.slice(5))+'</text>':'';}).join('');
        var axes=[0,.25,.5,.75,1].map(function(part){var value=min+(max-min)*part; return '<path d="M'+left+' '+y(value)+'H'+right+'"/><text x="39" y="'+(y(value)+4)+'" text-anchor="end">'+escapeHtml(Number(value.toFixed(1)))+'</text>';}).join('');
        app.querySelector('[data-trend-chart]').innerHTML = '<div class="tc-chart-tooltip" data-chart-tooltip aria-live="polite">Hover or focus a bar to see its value</div><svg viewBox="0 0 900 265" role="img" aria-label="'+escapeHtml(title+' by '+granularity+' period')+'"><defs><linearGradient id="tc-orange-fill" x1="0" y1="0" x2="1" y2="1"><stop stop-color="#ffb76d"/><stop offset="1" stop-color="#fc815d"/></linearGradient></defs><g class="tc-chart-grid">'+axes+'</g><g class="tc-chart-bars">'+bars+'</g>'+ticks+'</svg>';
    }

    function renderBreakdowns() {
        var report = state.report || {}, revenue = state.revenue || {};
        var retentionRows = (report.sources || []).filter(function (row) { return Number(row.visitors || 0) > 0; }).slice().sort(function (a, b) { return Number(b.retention_rate || 0) - Number(a.retention_rate || 0); });
        var winner = report.top_retention_source || null;
        var winnerTarget = app.querySelector('[data-retention-winner]');
        if (winnerTarget) winnerTarget.innerHTML = winner ? '<div class="tc-retention-winner"><span>HIGHEST OBSERVED RETURN RATE</span><strong>' + escapeHtml(winner.source || 'Direct') + '</strong><b>' + Number(winner.retention_rate || 0).toFixed(1) + '%</b><small>' + number(winner.returning_visitors) + ' of ' + number(winner.visitors) + ' visitors returned</small></div>' : '<div class="tc-retention-winner is-empty">More visits are needed before a source can be highlighted.</div>';
        var retentionTarget = app.querySelector('[data-retention-overview]');
        if (retentionTarget) retentionTarget.innerHTML = retentionRows.length ? retentionRows.slice(0, 6).map(function (row, index) {
            var rate = Math.max(0, Math.min(100, Number(row.retention_rate || 0)));
            return '<div class="tc-retention-row"><div><b class="tc-retention-rank">' + String(index + 1).padStart(2, '0') + '</b><strong>' + escapeHtml(row.source || 'Direct') + '</strong><span>' + number(row.returning_visitors) + ' of ' + number(row.visitors) + ' returned</span></div><div class="tc-retention-meter"><i style="width:' + rate.toFixed(1) + '%"></i></div><b>' + rate.toFixed(1) + '%</b></div>';
        }).join('') : '<div class="tc-empty">Retention will appear after visitors return for another visit.</div>';
        var handleTarget = app.querySelector('[data-source-handles]');
        if (handleTarget) handleTarget.innerHTML = (report.source_handles || []).length ? (report.source_handles || []).slice(0, 6).map(function (row) {
            return '<div class="tc-attribution-row"><strong>' + escapeHtml(row.source || 'Social') + ' · @' + escapeHtml(row.handle || '') + '</strong><span>' + number(row.visitors) + ' visitors</span></div>';
        }).join('') : '<div class="tc-empty">A referring account appears when the shared link or referrer exposes a handle.</div>';
        var locationTarget = app.querySelector('[data-locations]');
        if (locationTarget) locationTarget.innerHTML = (report.locations || []).length ? (report.locations || []).slice(0, 6).map(function (row) {
            return '<div class="tc-attribution-row"><strong>' + escapeHtml([row.city, row.country_code].filter(Boolean).join(', ')) + '</strong><span>' + number(row.visitors) + ' visitors</span></div>';
        }).join('') : '<div class="tc-empty">No trusted location data has been received yet.</div>';
        var source = breakdownView.source, path = breakdownView.path, activity = breakdownView.activity, outcome = breakdownView.outcome;
        app.querySelectorAll('[data-breakdown]').forEach(function (button) { button.classList.toggle('is-active', breakdownView[button.getAttribute('data-breakdown')] === button.getAttribute('data-breakdown-view')); });
        var sourceRows = source === 'source' ? (report.sources || []).map(function (row) { return { label: row.source, visits: row.visits, conversions: row.conversions }; }) : ((report.source_breakdowns || {})[source] || []);
        app.querySelector('[data-sources]').innerHTML = (report.source_breakdowns || {})[source] === null ? '<div class="tc-empty">Loading ' + escapeHtml(source) + ' data…</div>' : rankRows(sourceRows, 'label', 'visits', 'visits', 'No ' + source + ' data yet.', source);
        app.querySelector('[data-source-note]').textContent = source === 'source' ? 'Based on the first page of each visit.' : source === 'referrer' ? 'Referrer hosts recorded when a visit begins. “Not set” includes direct visits.' : 'UTM ' + (source === 'keyword' ? 'term' : 'campaign') + ' recorded when a visit begins.';
        var pathRows = path === 'page' ? report.pages || [] : path === 'entry' ? report.entry_pages || [] : (report.dropoffs || []).map(function (row) { return { page_path: row.page, visits: row.visitors, sub: row.form ? 'Form open: ' + row.form : '' }; });
        app.querySelector('[data-pages]').innerHTML = rankRows(pathRows, 'page_path', 'visits', path === 'exit' ? 'people' : 'visits', 'No page data yet.', path);
        app.querySelector('[data-path-note]').textContent = path === 'page' ? 'Visits with a view of each page.' : path === 'entry' ? 'First viewed page of each visit.' : 'Last observed page of ended visits among the 50 most recent tracked visitors.';
        var activityRows = activity === 'click' ? (report.top_clicks || []).map(function (row) { return { label: row.label || row.element || 'Button or link', sub: row.page_path, count: row.clicks }; }) : activity === 'form' ? (report.forms || []).map(function (row) { return { label: row.label || 'Form', sub: (row.page_path || '/') + ' · ' + String(row.event_type || '').replace('form_', ''), count: row.visits }; }) : (report.scroll || []).map(function (row) { return { label: row.scroll_depth + '% reached', count: row.visitors }; });
        app.querySelector('[data-heatmap]').innerHTML = rankRows(activityRows, 'label', 'count', activity === 'scroll' ? 'visits' : activity === 'form' ? 'visits' : 'clicks', 'No tracked ' + activity + ' activity yet.');
        app.querySelector('[data-activity-note]').textContent = activity === 'click' ? 'Tracked clicks on buttons and links.' : activity === 'form' ? 'Form starts, attempts, and submissions are separate events.' : 'Visits with a recorded scroll checkpoint on any page. Open Heatmaps for page-specific visible reach, including the initial screen.';
        var outcomeRows = outcome === 'payments' ? (revenue.sources || []).map(function (row) { return { label: row.source || 'Unattributed', count: row.orders || 0 }; }) : (report.dropoffs || []).map(function (row) { return { label: row.page || '/', sub: row.form ? 'Form open: ' + row.form : 'Last observed page', count: row.visitors }; });
        app.querySelector('[data-dropoffs]').innerHTML = outcome === 'payments' && !revenue.enabled ? '<div class="tc-empty">Enable Revenue Insights to see payment sources.</div>' : rankRows(outcomeRows, 'label', 'count', outcome === 'payments' ? 'payments' : 'people', 'No ' + (outcome === 'payments' ? 'paid payments' : 'ended visits') + ' in this period.');
        app.querySelector('[data-outcome-note]').textContent = outcome === 'payments' ? 'Payment source labels come from WooCommerce or connected gateway records. They are not matched to tracked visitor sessions.' : 'Based on the 50 most recent tracked visitors. Last seen is an observation, not proof of abandonment.';
    }

    function rankRows(rows, labelKey, valueKey, unit, empty, drillType) {
        if (!rows.length) return '<div class="tc-empty">' + escapeHtml(empty) + '</div>';
        var max = Math.max.apply(null, rows.map(function (row) { return Number(row[valueKey] || 0); }).concat([1]));
        return rows.slice().sort(function (a, b) { return Number(b[valueKey] || 0) - Number(a[valueKey] || 0); }).slice(0, 8).map(function (row) {
            var percent = Math.max(1, Number(row[valueKey] || 0) / max * 100);
            var tag = drillType ? 'button' : 'div';
            return '<' + tag + ' type="button" class="tc-rank"' + (drillType ? ' data-drill-type="' + escapeHtml(drillType) + '" data-drill-value="' + escapeHtml(row[labelKey] || '') + '" title="Show matching recent sessions"' : '') + '><div class="tc-rank__top"><span title="' + escapeHtml(row[labelKey]) + '">' + escapeHtml(row[labelKey] || 'Unknown') + (row.sub ? '<small>' + escapeHtml(row.sub) + '</small>' : '') + '</span><strong>' + number(row[valueKey]) + ' ' + unit + '</strong></div><div class="tc-rank__track"><i style="width:' + percent.toFixed(1) + '%"></i></div></' + tag + '>';
        }).join('');
    }

    function renderSessionBody(session) {
        var visit=session.visit, previousDay='', verbs={page:'Viewed page',click:'Clicked',form_start:'Started form',form_attempt:'Tried to submit',conversion:'Completed action'};
        var icons={page:'◉',click:'↗',form_start:'▤',form_attempt:'▤',conversion:'✓'};
        var timeline=(visit.steps||[]).map(function(step){
            var day=String(step.time||'').slice(0,10), heading=day!==previousDay ? '<li class="tc-thread-day">'+escapeHtml(day)+' · UTC</li>' : ''; previousDay=day;
            var kind=step.type==='conversion'?'is-success':step.type==='page'?'is-page':'is-action';
            return heading+'<li class="tc-thread-event '+kind+'"><time>'+escapeHtml(String(step.time||'').slice(11,16))+'</time><i aria-hidden="true">'+(icons[step.type]||'•')+'</i><div><strong>'+escapeHtml(verbs[step.type]||'Activity')+'</strong><span>'+escapeHtml(step.label||step.page||'')+'</span><small>'+escapeHtml(step.page||'/')+'</small></div></li>';
        }).join('');
        var attribution=[['Platform',visit.source||'Direct'],['Shared by',visit.source_handle?'@'+visit.source_handle:'Not set'],['Referrer',visit.referrer||'Not set'],['Campaign',visit.campaign||'Not set'],['Keyword',visit.keyword||'Not set'],['Location',visitLocation(visit)]];
        var attribRows=attribution.map(function(row){return '<div class="tc-thread-fact"><span>'+escapeHtml(row[0])+'</span><strong>'+escapeHtml(row[1])+'</strong></div>';}).join('');
        var ending=visit.conversions?'<div class="tc-thread-outcome is-success">✓ &nbsp; This visitor completed an action in this session.</div>':'<div class="tc-thread-outcome">'+escapeHtml(visit.exit_label||'Last activity recorded')+'</div>';
        var route=(visit.page_paths||[]).slice(0,5).map(function(path){return '<span>'+escapeHtml(path)+'</span>';}).join('<b aria-hidden="true">›</b>');
        return '<div class="tc-thread-layout"><aside class="tc-thread-profile-card"><div class="tc-thread-identity">'+avatarHtml(session,'tc-thread-avatar')+'<div><span>ANONYMOUS VISITOR</span><h3>'+escapeHtml(session.visitor)+'</h3><small>Session '+escapeHtml(visit.id||'')+'</small></div></div><div class="tc-thread-profile-list"><div><span>First visit</span><strong>'+escapeHtml(dateTime(session.firstSeen||visit.started))+'</strong></div><div><span>Last visit</span><strong>'+escapeHtml(dateTime(session.lastSeen||visit.last_seen))+'</strong></div><div><span>Location</span><strong>'+escapeHtml(visitLocation(visit))+'</strong></div><div><span>Original source</span><strong>'+escapeHtml(session.source||'Direct')+'</strong></div></div><div class="tc-thread-profile-summary"><span><b>'+number(session.totalVisits)+'</b> visits</span><span><b>'+number(visit.pageviews)+'</b> pages</span><span><b>'+number(visit.conversions)+'</b> results</span></div></aside><div class="tc-thread-activity"><div class="tc-thread-panel-title"><h3>Activity timeline</h3><span>'+number((visit.steps||[]).length)+' events · '+escapeHtml(dateTime(visit.started))+'</span></div><ol>'+timeline+'</ol>'+ending+'<p class="tc-note">Up to 40 recent actions shown. Times are UTC; an ended visit is inferred after 30 minutes without activity.</p></div><aside class="tc-thread-aside"><section class="tc-thread-facts"><h3>↗ &nbsp; Attribution</h3>'+attribRows+'</section><section class="tc-thread-facts tc-thread-route"><h3>◇ &nbsp; Visitor route</h3><div class="tc-thread-route-steps">'+(route||'<span>No page route yet</span>')+'</div></section><section class="tc-thread-facts"><h3>↻ &nbsp; Retention</h3><div class="tc-thread-fact"><span>Total visits</span><strong>'+number(session.totalVisits)+'</strong></div><div class="tc-thread-fact"><span>Returned</span><strong>'+(Number(session.totalVisits)>1?'Yes':'Not yet')+'</strong></div></section></aside></div>';
    }
    var journeyReturnFocus=null;
    function openJourney(index) {
        var session=(state.sessions||[])[index]; if(!session) return;
        journeyReturnFocus=document.activeElement;
        var sheet=app.querySelector('[data-journey-sheet]');
        sheet.querySelector('[data-journey-detail]').innerHTML=renderSessionBody(session);
        sheet.hidden=false; sheet.querySelector('[data-close-journey].tc-icon-button').focus();
    }
    function closeJourney() {
        app.querySelector('[data-journey-sheet]').hidden=true;
        app.querySelector('[data-journey-detail]').innerHTML='';
        if(journeyReturnFocus && journeyReturnFocus.isConnected) journeyReturnFocus.focus();
    }

    function metricFor(experiment, variantId) {
        return (experiment.metrics || []).find(function (row) { return Number(row.id) === Number(variantId); }) || {};
    }

    function renderExperiments() {
        var list = app.querySelector('[data-experiments]');
        var experiments = state.experiments || [];
        if (!experiments.length) {
            list.innerHTML = '<div class="tc-card tc-empty"><strong>No experiments yet.</strong><p>Select two or more pages and decide how traffic should be shared.</p></div>';
            return;
        }
        list.innerHTML = experiments.map(function (experiment) {
            var active = experiment.status === 'active';
            var variants = (experiment.variants || []).map(function (variant) {
                var metric = metricFor(experiment, variant.id);
                var rules = variant.rules || {};
                var rule = [rules.utm_source, rules.utm_medium, rules.utm_campaign].filter(Boolean).join(' / ');
                var interval = metric.impressions ? ' · 95% range ' + Number(metric.confidence_low || 0).toFixed(1) + '–' + Number(metric.confidence_high || 0).toFixed(1) + '%' : '';
                var uplift = !variant.is_control && Number(metric.relative_uplift || 0) ? ' · ' + (Number(metric.relative_uplift) > 0 ? '+' : '') + Number(metric.relative_uplift).toFixed(1) + '% uplift' : '';
                return '<div class="tc-variant"><strong>' + escapeHtml(variant.name || variant.post_title) + '</strong>' +
                    '<span>' + escapeHtml(variant.post_title || ('Page #' + variant.wp_page_id)) + '</span>' +
                    '<span><b>' + Number(metric.conversion_rate || 0).toFixed(2) + '%</b> · ' + number(metric.visitors) + ' visitors · ' + Number(variant.weight || 0).toFixed(1) + '% traffic' + interval + uplift + '</span>' +
                    (rule ? '<span>UTM: ' + escapeHtml(rule) + '</span>' : '') + '</div>';
            }).join('');
            return '<article class="tc-experiment" data-experiment-id="' + Number(experiment.id) + '">' +
                '<div class="tc-experiment__top"><div><h3>' + escapeHtml(experiment.name) + '</h3><div class="tc-experiment__meta"><span>' + escapeHtml(experiment.target_path) + '</span><span>' + (experiment.sticky == 1 ? 'Sticky' : 'Rotating') + '</span><span>' + (experiment.smart_traffic == 1 ? 'Smart Traffic' : 'Fixed allocation') + '</span></div></div>' +
                '<div class="tc-experiment__actions"><span class="tc-status ' + (active ? 'tc-status--active' : '') + '">' + escapeHtml(experiment.status) + '</span><button type="button" class="button" data-edit-experiment="' + Number(experiment.id) + '">Edit</button><button type="button" class="button" data-toggle-experiment="' + Number(experiment.id) + '" data-status="' + (active ? 'paused' : 'active') + '">' + (active ? 'Pause' : 'Start') + '</button></div></div>' +
                '<div class="tc-variants">' + variants + '</div></article>';
        }).join('');
    }

    function renderInsights() {
        var insights = state.insights || [];
        app.querySelector('[data-insights]').innerHTML = insights.length ? insights.map(function (insight, index) {
            var canCreate = !!(state.modules || {}).ai_conversion_agent && Number(insight.experiment_id || 0) > 0 && insight.suggested_copy;
            return '<article class="tc-insight"><span class="tc-severity">' + escapeHtml(insight.severity || 'info') + '</span><h3>' + escapeHtml(insight.title) + '</h3><p>' + escapeHtml(insight.message) + '</p>' +
                (canCreate ? '<button type="button" class="button tc-button" data-create-variant="' + index + '">Create Variant B</button>' : '') + '</article>';
        }).join('') : '<div class="tc-empty">No recommendations yet. Keep collecting data.</div>';
    }

    function pageOptions(selected) {
        return '<option value="">Choose a page</option>' + (state.pages || []).map(function (page) {
            return '<option value="' + Number(page.id) + '"' + (Number(selected) === Number(page.id) ? ' selected' : '') + '>' + escapeHtml(page.title + (page.status !== 'publish' ? ' — ' + page.status : '')) + '</option>';
        }).join('');
    }

    function addVariantRow(variant) {
        variant = variant || {};
        var row = document.createElement('div');
        row.className = 'tc-variant-row';
        row.setAttribute('data-variant-id', Number(variant.id || 0));
        var rules = variant.rules || {};
        row.innerHTML =
            '<label><span>Page</span><select data-field="wp_page_id" required>' + pageOptions(variant.wp_page_id) + '</select></label>' +
            '<label><span>Traffic %</span><input type="number" min="0" max="100" step="0.01" data-field="weight" value="' + escapeHtml(variant.weight == null ? 50 : variant.weight) + '"></label>' +
            '<label><span>UTM source</span><input type="text" data-field="utm_source" value="' + escapeHtml(rules.utm_source || '') + '" placeholder="google"></label>' +
            '<label><span>UTM medium</span><input type="text" data-field="utm_medium" value="' + escapeHtml(rules.utm_medium || '') + '" placeholder="cpc"></label>' +
            '<label><span>Campaign</span><input type="text" data-field="utm_campaign" value="' + escapeHtml(rules.utm_campaign || '') + '" placeholder="launch"></label>' +
            '<button type="button" data-remove-variant aria-label="Remove variant">×</button>';
        app.querySelector('[data-variant-rows]').appendChild(row);
    }

    function openExperiment(experiment) {
        var modal = app.querySelector('[data-experiment-modal]');
        var form = app.querySelector('[data-experiment-form]');
        form.reset();
        form.elements.experiment_id.value = experiment ? experiment.id : 0;
        form.elements.name.value = experiment ? experiment.name : '';
        form.elements.target_path.value = experiment ? experiment.target_path : '/';
        form.elements.primary_goal.value = experiment ? experiment.primary_goal : 'conversion';
        form.elements.cookie_days.value = experiment ? experiment.cookie_days : 30;
        form.elements.sticky.checked = experiment ? Number(experiment.sticky) === 1 : !!(state.settings || {}).sticky_assignments;
        form.elements.smart_traffic.checked = experiment ? Number(experiment.smart_traffic) === 1 : false;
        app.querySelector('[data-variant-rows]').innerHTML = '';
        if (experiment && experiment.variants) {
            experiment.variants.forEach(addVariantRow);
        } else {
            addVariantRow({ weight: 50 });
            addVariantRow({ weight: 50 });
        }
        modal.hidden = false;
        window.setTimeout(function () { form.elements.name.focus(); }, 20);
    }

    function closeExperiment() {
        app.querySelector('[data-experiment-modal]').hidden = true;
    }

    function collectVariants() {
        return Array.prototype.map.call(app.querySelectorAll('.tc-variant-row'), function (row, index) {
            var pageSelect = row.querySelector('[data-field="wp_page_id"]');
            var page = (state.pages || []).find(function (item) { return Number(item.id) === Number(pageSelect.value); }) || {};
            return {
                id: Number(row.getAttribute('data-variant-id') || 0),
                wp_page_id: Number(pageSelect.value || 0),
                name: index === 0 ? 'Control' : 'Variant ' + String.fromCharCode(65 + index),
                page_title: page.title || '',
                weight: Number(row.querySelector('[data-field="weight"]').value || 0),
                utm_source: row.querySelector('[data-field="utm_source"]').value,
                utm_medium: row.querySelector('[data-field="utm_medium"]').value,
                utm_campaign: row.querySelector('[data-field="utm_campaign"]').value
            };
        }).filter(function (variant) { return variant.wp_page_id > 0; });
    }

    function renderAll() {
        renderReport();
        renderExperiments();
        renderInsights();
    }

    app.addEventListener('click', function (event) {
        var tab = event.target.closest('[data-tab]');
        if (tab) showTab(tab.getAttribute('data-tab'));
        var metric = event.target.closest('[data-trend-metric]');
        if (metric) { trendMetric = metric.getAttribute('data-trend-metric'); renderTrend(); }
        var breakdown = event.target.closest('[data-breakdown]');
        if (breakdown) {
            var group = breakdown.getAttribute('data-breakdown'), view = breakdown.getAttribute('data-breakdown-view');
            breakdownView[group] = view;
            if (group === 'source' && view !== 'source' && !Object.prototype.hasOwnProperty.call((state.report || {}).source_breakdowns || {}, view)) {
                var activeReport = state.report;
                activeReport.source_breakdowns = activeReport.source_breakdowns || {};
                activeReport.source_breakdowns[view] = null;
                app.querySelector('[data-sources]').innerHTML = '<div class="tc-empty">Loading ' + escapeHtml(view) + ' data…</div>';
                request('ugentic_conversion_get_breakdown', { days: app.querySelector('#tc-range').value, dimension: view })
                    .done(function (data) {
                        if (state.report !== activeReport) return;
                        activeReport.source_breakdowns[view] = data.rows || [];
                        if (breakdownView.source === view) renderBreakdowns();
                    }).fail(function (error) {
                        if (state.report !== activeReport) return;
                        delete activeReport.source_breakdowns[view];
                        toast(error.message || boot.i18n.error, true);
                    });
            } else renderBreakdowns();
        }
        if (event.target.closest('[data-close-journey]')) closeJourney();
        var preview = event.target.closest('[data-view-session]');
        if (preview) {
            openJourney(Number(preview.getAttribute('data-view-session')));
        }
        var drill = event.target.closest('[data-drill-type]');
        if (drill) {
            var type = drill.getAttribute('data-drill-type'), value = drill.getAttribute('data-drill-value');
            var match = function (session) {
                var visit = session.visit || {};
                if (type === 'source') return (visit.source || 'Direct') === value;
                if (type === 'referrer' || type === 'campaign' || type === 'keyword') return (visit[type] || 'Not set') === value;
                if (type === 'entry') return visit.entry_page === value;
                if (type === 'page') return (visit.page_paths || []).indexOf(value) !== -1;
                return type === 'exit' && visit.status === 'ended' && visit.last_page === value && (visit.open_form || !visit.conversions);
            };
            var matches = 0;
            app.querySelectorAll('.tc-session').forEach(function (card) {
                var show = match((state.sessions || [])[Number(card.getAttribute('data-session-index'))] || {});
                card.hidden = !show;
                if (show) matches++;
            });
            app.querySelector('[data-journey-count]').textContent = number(matches) + ' matching recent session threads · ' + type + ': ' + value;
            app.querySelector('[data-clear-journey-filter]').hidden = false;
            showTab('journeys');
        }
        if (event.target.closest('[data-clear-journey-filter]')) clearJourneyFilter();
        if (event.target.closest('[data-open-experiment]')) {
            if (!(state.modules || {}).ab_testing) {
                window.location.href = boot.modulesUrl || 'admin.php?page=ugentic-modules';
            } else {
                openExperiment(null);
            }
        }
        if (event.target.closest('[data-close-experiment]')) closeExperiment();
        if (event.target.closest('[data-add-variant]')) addVariantRow({ weight: 0 });
        var remove = event.target.closest('[data-remove-variant]');
        if (remove) {
            if (app.querySelectorAll('.tc-variant-row').length <= 2) {
                toast('An experiment needs at least two pages.', true);
            } else {
                remove.closest('.tc-variant-row').remove();
            }
        }
        var edit = event.target.closest('[data-edit-experiment]');
        if (edit) {
            var experiment = (state.experiments || []).find(function (item) { return Number(item.id) === Number(edit.getAttribute('data-edit-experiment')); });
            if (experiment) openExperiment(experiment);
        }
        var toggle = event.target.closest('[data-toggle-experiment]');
        if (toggle) {
            toggle.disabled = true;
            request('ugentic_conversion_toggle_experiment', {
                experiment_id: toggle.getAttribute('data-toggle-experiment'),
                status: toggle.getAttribute('data-status')
            }).done(function (data) {
                state.experiments = data.experiments || [];
                renderExperiments();
                toast('Experiment updated.');
            }).fail(function (error) {
                toggle.disabled = false;
                toast(error.message || boot.i18n.error, true);
            });
        }
        var create = event.target.closest('[data-create-variant]');
        if (create) {
            var insight = (state.insights || [])[Number(create.getAttribute('data-create-variant'))];
            create.disabled = true;
            create.textContent = boot.i18n.creating;
            request('ugentic_conversion_create_variant', {
                experiment_id: insight.experiment_id,
                insight: JSON.stringify(insight)
            }).done(function (data) {
                state.experiments = data.experiments || (state.experiments || []).map(function (item) {
                    return Number(item.id) === Number(data.experiment.id) ? data.experiment : item;
                });
                renderExperiments();
                toast(data.message || boot.i18n.saved);
                if (data.edit_url) window.open(data.edit_url, '_blank', 'noopener');
            }).fail(function (error) {
                create.disabled = false;
                create.textContent = 'Create Variant B';
                toast(error.message || boot.i18n.error, true);
            });
        }
    });

    function clearJourneyFilter() {
        app.querySelectorAll('.tc-session').forEach(function (card) { card.hidden = false; });
        app.querySelector('[data-journey-count]').textContent = number((state.sessions || []).length) + ' recent session threads';
        app.querySelector('[data-clear-journey-filter]').hidden = true;
    }

    app.querySelector('[data-experiment-form]').addEventListener('submit', function (event) {
        event.preventDefault();
        var form = event.currentTarget;
        var variants = collectVariants();
        if (variants.length < 2 || new Set(variants.map(function (item) { return item.wp_page_id; })).size < 2) {
            toast(boot.i18n.needVariants, true);
            return;
        }
        var submit = form.querySelector('[type="submit"]');
        submit.disabled = true;
        request('ugentic_conversion_save_experiment', {
            experiment_id: form.elements.experiment_id.value,
            name: form.elements.name.value,
            target_path: form.elements.target_path.value,
            primary_goal: form.elements.primary_goal.value,
            cookie_days: form.elements.cookie_days.value,
            sticky: form.elements.sticky.checked ? 1 : '',
            smart_traffic: form.elements.smart_traffic.checked ? 1 : '',
            variants: JSON.stringify(variants)
        }).done(function (data) {
            state.experiments = data.experiments || [];
            renderExperiments();
            closeExperiment();
            showTab('experiments');
            toast('Experiment saved.');
        }).fail(function (error) {
            toast(error.message || boot.i18n.error, true);
        }).always(function () { submit.disabled = false; });
    });

    app.querySelector('[data-chat-form]').addEventListener('submit', function (event) {
        event.preventDefault();
        var question = app.querySelector('#tc-question');
        var answer = app.querySelector('[data-chat-answer]');
        if (!question.value.trim()) return;
        answer.textContent = 'Analyzing aggregate website data…';
        request('ugentic_conversion_data_chat', {
            question: question.value,
            days: app.querySelector('#tc-range').value
        }).done(function (data) {
            answer.textContent = data.answer;
        }).fail(function (error) {
            answer.textContent = error.message || boot.i18n.error;
        });
    });

    var analysisButton = app.querySelector('[data-ai-analyze]');
    var analysisOutput = app.querySelector('[data-ai-analysis]');
    var overviewButton = app.querySelector('[data-ai-overview]');
    var overviewOutput = app.querySelector('[data-ai-overview-answer]');
    var analysisRange = '';
    if (!(state.modules || {}).ai_conversion_agent) {
        analysisButton.disabled = true;
        analysisOutput.textContent = 'Enable AI Growth Assistant in Modules to analyze your conversion data.';
        overviewButton.disabled = true;
        overviewOutput.textContent = analysisOutput.textContent;
    } else if (!state.aiConnected) {
        analysisButton.disabled = true;
        analysisOutput.textContent = 'Connect an AI provider in Tahmir settings to see an AI conversion analysis.';
        overviewButton.disabled = true;
        overviewOutput.textContent = analysisOutput.textContent;
    } else {
        overviewOutput.textContent = 'Ready to analyze this report.';
    }
    function ensureAnalysis(force) {
        if (!(state.modules || {}).ai_conversion_agent || !state.aiConnected) return;
        var range = app.querySelector('#tc-range').value;
        if (!force && analysisRange === range) return;
        analysisRange = range;
        analysisButton.disabled = true;
        overviewButton.disabled = true;
        analysisOutput.textContent = overviewOutput.textContent = 'Analyzing tracked visits, clicks, forms and exits…';
        request('ugentic_conversion_ai_analysis', { days: range })
            .done(function (data) { analysisOutput.textContent = overviewOutput.textContent = data.analysis || ''; })
            .fail(function (error) { analysisRange = ''; analysisOutput.textContent = overviewOutput.textContent = error.message || boot.i18n.error; })
            .always(function () { analysisButton.disabled = overviewButton.disabled = false; });
    }
    analysisButton.addEventListener('click', function () { ensureAnalysis(true); });
    overviewButton.addEventListener('click', function () { ensureAnalysis(true); });

    function refreshReport(event) {
        if (boot.demo) { toast('Sample data — upgrade to Pro to report on your website.'); return; }
        request('ugentic_conversion_get_report', { days: app.querySelector('#tc-range').value, refresh: event && event.type === 'click' ? 1 : 0 }).done(function (data) {
            state.report = data.report;
            state.revenue = data.revenue;
            state.insights = data.insights;
            renderReport();
            renderInsights();
            if (overviewOutput && !analysisButton.disabled) overviewOutput.textContent = 'Ready to analyze this report.';
            analysisRange = '';
        }).fail(function (error) { toast(error.message || boot.i18n.error, true); });
    }
    app.querySelector('#tc-range').addEventListener('change', refreshReport);
    app.querySelector('#tc-granularity').addEventListener('change', renderTrend);
    app.querySelector('[data-refresh-report]').addEventListener('click', refreshReport);

    document.addEventListener('keydown', function (event) {
        if(!app.querySelector('[data-journey-sheet]').hidden) {
            if(event.key==='Escape') closeJourney();
            if(event.key==='Tab') { event.preventDefault(); app.querySelector('[data-journey-sheet] .tc-icon-button').focus(); }
        }
        if (event.key === 'Escape' && !app.querySelector('[data-experiment-modal]').hidden) closeExperiment();
    });

    ['mouseover','focusin'].forEach(function(type){app.addEventListener(type,function(event){var bar=event.target.closest('[data-chart-point]');if(bar)app.querySelector('[data-chart-tooltip]').textContent=bar.getAttribute('data-chart-point');});});
    renderAll();
    if(window.TahmirHeatmaps) window.TahmirHeatmaps(app, boot, state, request);
    if(boot.demo) {
        app.classList.add('is-demo');
        app.insertAdjacentHTML('afterbegin','<div class="tc-demo-banner"><div><strong>Explore the Pro analytics demo</strong><span>Sample data only · your website is not being tracked by this demo.</span></div><a class="button tc-button--primary" href="https://tahmir.com/upgrade" target="_blank" rel="noopener noreferrer">Upgrade to Pro ↗</a></div>');
        overviewOutput.textContent='Example recommendation: visitors in this sample often stop on /pricing after opening the contact form. Test clearer pricing and fewer form fields, then compare completed visits. Live AI analysis requires Pro and a connected AI provider.';
    }
    var initialTab = boot.contextTab || app.getAttribute('data-context-tab') || window.location.hash.replace('#', '');
    if (['overview', 'journeys', 'heatmaps', 'experiments', 'intelligence'].indexOf(initialTab) !== -1) {
        showTab(initialTab);
    }
}(jQuery));
