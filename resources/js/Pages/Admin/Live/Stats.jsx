import '../../../../css/live-monitor.css';
import '../../../../css/visit-stats.css';

import { Head, Link, usePage } from '@inertiajs/react';
import {
    ApartmentOutlined,
    ArrowLeftOutlined,
    FullscreenExitOutlined,
    FullscreenOutlined,
    LoginOutlined,
    LogoutOutlined,
    TeamOutlined,
    UserOutlined,
} from '@ant-design/icons';
import AnimatedNumber, { BumpGlow } from '../../../Components/Live/AnimatedNumber';
import { FemaleFigureIcon, MaleFigureIcon } from '../../../Components/Live/GenderIcons';
import LiveClock from '../../../Components/Live/LiveClock';
import useFullscreen from '../../../hooks/useFullscreen';
import useIncrease from '../../../hooks/useIncrease';
import useInertiaPoll from '../../../hooks/useInertiaPoll';
import { route } from '../../../routes';
import { formatNumber, formatPercent, GROUP_TONES, initialsOf, share } from '../../../utils/liveFormat';

const POLL_MS = 3000;

function PanelTitle({ title }) {
    return (
        <div className="vs-panel-title">
            <h2>{title}</h2>
        </div>
    );
}

function KpiCard({ tone, icon: Icon, label, value, note, noteTone = false }) {
    // Figma: yangi o‘tishdan keyin izoh oldida "▲ +1 hozirgina" (10 soniya).
    const bump = useIncrease(value);

    return (
        <article className={`vs-kpi vs-tone--${tone}`}>
            <div className="vs-kpi-head">
                <span className="vs-icon-box" aria-hidden="true">
                    <Icon />
                </span>
                <div>
                    <p className="vs-kpi-label">{label}</p>
                </div>
            </div>
            <p className="vs-kpi-value"><AnimatedNumber value={value ?? 0} /></p>
            <p className={`vs-kpi-note${noteTone ? ' vs-kpi-note--tone' : ''}`}>
                {bump ? (
                    <>
                        <span className="vs-kpi-bump">▲ +{formatNumber(bump.delta)} hozirgina</span>
                        {'  ·  '}
                    </>
                ) : null}
                {note}
            </p>
            <BumpGlow value={value} />
        </article>
    );
}

function OriginPanel({ stats }) {
    const entered = stats.entered ?? 0;
    const localShare = share(stats.local, entered);
    const ring = entered > 0
        ? `conic-gradient(var(--vs-blue) 0 ${localShare}%, var(--vs-gold) ${localShare}% 100%)`
        : 'var(--lm-row)';

    const rows = [
        { key: 'local', label: 'Mahalliy', tone: 'blue', value: stats.local },
        { key: 'foreign', label: 'Xorijiy', tone: 'gold', value: stats.foreign },
    ];

    return (
        <section className="vs-panel vs-origin" aria-label="Mahalliy va xorijiy">
            <PanelTitle title="Mahalliy va xorijiy" />
            <div className="vs-donut" style={{ background: ring }}>
                <div className="vs-donut-center">
                    <p className="vs-donut-total"><AnimatedNumber value={entered} /></p>
                </div>
            </div>
            <ul className="vs-legend">
                {rows.map((row) => (
                    <li key={row.key} className={`vs-legend-row vs-tone--${row.tone}`}>
                        <span className="vs-dot" aria-hidden="true" />
                        <span className="vs-legend-label">{row.label}</span>
                        <span className="vs-legend-value"><AnimatedNumber value={row.value ?? 0} /></span>
                        <span className="vs-legend-pct">{formatPercent(row.value, entered)}</span>
                        <BumpGlow value={row.value} />
                    </li>
                ))}
            </ul>
        </section>
    );
}

function GenderCard({ tone, icon: Icon, label, value, total }) {
    return (
        <article className={`vs-gender-card vs-tone--${tone}`}>
            <div className="vs-gender-head">
                <span className="vs-icon-box vs-icon-box--sm" aria-hidden="true">
                    <Icon />
                </span>
                <div className="vs-gender-labels">
                    <p className="vs-gender-label">{label}</p>
                </div>
                <span className="vs-pill">{formatPercent(value, total)}</span>
            </div>
            <p className="vs-gender-value"><AnimatedNumber value={value ?? 0} /></p>
            <BumpGlow value={value} />
        </article>
    );
}

function GenderPanel({ stats }) {
    const total = (stats.male ?? 0) + (stats.female ?? 0);
    const malePct = Math.round(share(stats.male, total));

    return (
        <section className="vs-panel vs-gender" aria-label="Erkaklar va ayollar">
            <PanelTitle title="Erkaklar va ayollar" />
            <div className="vs-gender-cards">
                <GenderCard tone="blue" icon={MaleFigureIcon} label="Erkaklar" value={stats.male} total={total} />
                <GenderCard tone="pink" icon={FemaleFigureIcon} label="Ayollar" value={stats.female} total={total} />
            </div>
            <div className="vs-split">
                <div className={`vs-split-bar${total > 0 ? ' vs-split-bar--pink' : ''}`}>
                    <span className="vs-split-segment vs-tone--blue" style={{ width: `${share(stats.male, total)}%` }} />
                </div>
                <div className="vs-split-labels">
                    <span className="vs-tone--blue">Erkaklar {malePct}%</span>
                    <span className="vs-tone--pink">Ayollar {total > 0 ? 100 - malePct : 0}%</span>
                </div>
            </div>
            <div className="vs-inside">
                <div>
                    <p className="vs-inside-label">Hozir ichkarida</p>
                </div>
                <div className="vs-inside-values">
                    <span className="vs-inside-item vs-tone--blue">
                        <span className="vs-dot" aria-hidden="true" />
                        Erkak <strong><AnimatedNumber value={stats.inside_male ?? 0} /></strong>
                    </span>
                    <span className="vs-inside-item vs-tone--pink">
                        <span className="vs-dot" aria-hidden="true" />
                        Ayol <strong><AnimatedNumber value={stats.inside_female ?? 0} /></strong>
                    </span>
                </div>
            </div>
        </section>
    );
}

function GroupsPanel({ stats }) {
    const groups = stats.groups ?? [];
    // Foiz bugun kirganlarga nisbatan: guruhsiz xodimlar (qurilmadan kelganlar) bo‘lsa, chiziqda bo‘sh qism qoladi.
    const total = stats.entered ?? 0;
    const toned = groups.map((group, index) => ({ ...group, tone: GROUP_TONES[index % GROUP_TONES.length] }));

    return (
        <section className="vs-panel vs-groups" aria-label="Toifalar bo‘yicha">
            <PanelTitle title="Toifalar bo‘yicha" />
            <div className="vs-split-bar vs-split-bar--flex">
                {toned.map((group) => (
                    <span
                        key={group.id}
                        className={`vs-split-segment vs-tone--${group.tone}`}
                        style={{ width: `${share(group.entered, total)}%` }}
                    />
                ))}
            </div>
            {toned.length > 0 ? (
                <ul className="vs-group-list">
                    {toned.map((group) => (
                        <li key={group.id} className={`vs-group-row vs-tone--${group.tone}`}>
                            <span className="vs-icon-box vs-icon-box--row" aria-hidden="true">
                                <ApartmentOutlined />
                            </span>
                            <div className="vs-group-labels">
                                <p className="vs-group-name">{group.name}</p>
                            </div>
                            <div className="vs-group-numbers">
                                <p className="vs-group-value"><AnimatedNumber value={group.entered ?? 0} /></p>
                                <p className="vs-group-pct">{formatPercent(group.entered, total)}</p>
                            </div>
                            <BumpGlow value={group.entered} />
                        </li>
                    ))}
                </ul>
            ) : (
                <p className="vs-empty">Guruhlar hali yaratilmagan.</p>
            )}
        </section>
    );
}

function LastArrival({ arrival }) {
    const meta = arrival
        ? [arrival.country_name, arrival.position || arrival.department_name].filter(Boolean).join('  ·  ')
        : '';
    const gate = arrival ? [arrival.device_name, arrival.time].filter(Boolean).join('  ·  ') : '';

    return (
        <footer className="vs-ticker" aria-live="polite">
            <p className="vs-ticker-kicker">So‘nggi kirgan</p>
            {arrival ? (
                <>
                    <div className="vs-ticker-body">
                        <p className="vs-ticker-name">{arrival.employee_name}</p>
                        {meta ? <p className="vs-ticker-meta">{meta}</p> : null}
                    </div>
                    <p className="vs-ticker-gate">{gate}</p>
                </>
            ) : (
                <p className="vs-ticker-meta">Bugun hali kirish qayd etilmagan.</p>
            )}
        </footer>
    );
}

// `screen` — login'siz ochiq ekran (/screen/stats): qaytish tugmasi ochiq monitorga olib boradi.
export default function Stats({ stats = {}, screen = null }) {
    const { appName } = usePage().props;
    const brandName = appName || 'Biocontrol';
    const [fullscreen, toggleFullscreen] = useFullscreen();

    useInertiaPoll(['stats'], POLL_MS);

    return (
        <>
            <Head title="Tashrif statistikasi" />

            <div className="live-monitor visit-stats">
                <header className="lm-header">
                    <div className="lm-brand">
                        <span className="lm-brand-mark" aria-hidden="true">
                            <span>{initialsOf(brandName)}</span>
                        </span>
                        <div>
                            <h1 className="lm-brand-name">{brandName}  ·  Tashrif statistikasi</h1>
                            <p className="lm-brand-sub">Real vaqt rejimida yangilanadi</p>
                        </div>
                    </div>

                    <div className="lm-header-side">
                        <div className="lm-tools">
                            <Link
                                href={screen?.live_url ?? route('admin.live.index')}
                                className="lm-tool"
                                aria-label="Kirish monitoriga qaytish"
                                title="Kirish monitoriga qaytish"
                            >
                                <ArrowLeftOutlined aria-hidden="true" />
                            </Link>
                            <button
                                type="button"
                                className="lm-tool"
                                aria-label={fullscreen ? 'To‘liq ekrandan chiqish' : 'To‘liq ekran'}
                                title={fullscreen ? 'To‘liq ekrandan chiqish' : 'To‘liq ekran'}
                                onClick={toggleFullscreen}
                            >
                                {fullscreen
                                    ? <FullscreenExitOutlined aria-hidden="true" />
                                    : <FullscreenOutlined aria-hidden="true" />}
                            </button>
                        </div>
                        <span className="lm-live" role="status">
                            <span className="lm-live-dot" aria-hidden="true" />
                            JONLI
                        </span>
                        <LiveClock />
                    </div>
                </header>

                <section className="vs-kpis" aria-label="Bugungi ko‘rsatkichlar">
                    <KpiCard
                        tone="white"
                        icon={TeamOutlined}
                        label="Ro‘yxatdan o‘tganlar"
                        value={stats.registered}
                        note={`Bugun +${formatNumber(stats.registered_today)} ta yangi`}
                    />
                    <KpiCard
                        tone="green"
                        icon={LoginOutlined}
                        label="Bugun kirganlar"
                        value={stats.entered}
                        note={`So‘nggi 1 soatda +${formatNumber(stats.entered_last_hour)}`}
                        noteTone
                    />
                    <KpiCard
                        tone="blue"
                        icon={UserOutlined}
                        label="Hozir ichkarida"
                        value={stats.inside}
                        note={`Erkak ${formatNumber(stats.inside_male)}  ·  Ayol ${formatNumber(stats.inside_female)}`}
                        noteTone
                    />
                    <KpiCard
                        tone="red"
                        icon={LogoutOutlined}
                        label="Bugun chiqqanlar"
                        value={stats.exited}
                        note={`So‘nggi 1 soatda +${formatNumber(stats.exited_last_hour)}`}
                    />
                </section>

                <main className="vs-panels">
                    <OriginPanel stats={stats} />
                    <GenderPanel stats={stats} />
                    <GroupsPanel stats={stats} />
                </main>

                <LastArrival arrival={stats.last_arrival} />
            </div>
        </>
    );
}
