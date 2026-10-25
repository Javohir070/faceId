import '../../../../css/live-monitor.css';

import { Head, Link, usePage } from '@inertiajs/react';
import {
    ApartmentOutlined,
    ArrowLeftOutlined,
    BarChartOutlined,
    FullscreenExitOutlined,
    FullscreenOutlined,
    GlobalOutlined,
    HomeOutlined,
    LoginOutlined,
} from '@ant-design/icons';
import { useState } from 'react';
import AnimatedNumber, { BumpGlow } from '../../../Components/Live/AnimatedNumber';
import { FemaleFigureIcon, MaleFigureIcon } from '../../../Components/Live/GenderIcons';
import LiveClock from '../../../Components/Live/LiveClock';
import useFullscreen from '../../../hooks/useFullscreen';
import useInertiaPoll from '../../../hooks/useInertiaPoll';
import { route } from '../../../routes';
import { GROUP_TONES, initialsOf } from '../../../utils/liveFormat';

const POLL_MS = 3000;
const LOCAL_COUNTRY = 'UZ';
const GENDER_MALE = 1;
const GENDER_FEMALE = 2;

// Figma "Kirish monitori": 1-qator — Kirganlar + 3 ta guruh (OAV, Homiy, Ishtirokchi), 2-qator — kelib chiqish va jins.
// Guruhlar ko‘payib ketsa ham 1080px ekranda mehmon kartasi siqilmasin; to‘liq ro‘yxat "Tashrif statistikasi" sahifasida.
const GROUP_SLOTS = 3;

const enteredTile = { key: 'entered', label: 'Kirganlar', sub: 'Entered today', tone: 'green', icon: LoginOutlined };

const breakdownTiles = [
    { key: 'local', label: 'Mahalliy', sub: 'Local', tone: 'blue', icon: HomeOutlined },
    { key: 'foreign', label: 'Xorijiy', sub: 'International', tone: 'yellow', icon: GlobalOutlined },
    { key: 'male', label: 'Erkaklar', sub: 'Male', tone: 'blue', icon: MaleFigureIcon },
    { key: 'female', label: 'Ayollar', sub: 'Female', tone: 'pink', icon: FemaleFigureIcon },
];

function isForeign(pass) {
    return Boolean(pass?.country) && pass.country !== LOCAL_COUNTRY;
}

function organizationOf(pass) {
    return pass.position || pass.department_name || '';
}

/** Oxirgi kirgan mehmon qo‘shilgan plitkalar ajratib ko‘rsatiladi (Figma'dagi rangli ramka). */
function activeTileKeys(pass) {
    if (!pass) {
        return new Set();
    }

    const keys = new Set(['entered', isForeign(pass) ? 'foreign' : 'local']);

    if (pass.gender === GENDER_MALE) {
        keys.add('male');
    } else if (pass.gender === GENDER_FEMALE) {
        keys.add('female');
    }

    if (pass.group_id) {
        keys.add(`group-${pass.group_id}`);
    }

    return keys;
}

function Avatar({ pass, className }) {
    const [failedUrl, setFailedUrl] = useState(null);
    const showPhoto = pass.face_url && failedUrl !== pass.face_url;

    return (
        <span className={`lm-avatar ${className}`}>
            {showPhoto ? (
                <img src={pass.face_url} alt="" onError={() => setFailedUrl(pass.face_url)} />
            ) : (
                <span className="lm-avatar-initials" aria-hidden="true">
                    {initialsOf(pass.employee_name)}
                </span>
            )}
        </span>
    );
}

// Bayroq o‘rni: Windows emoji bayroqlarni ko‘rsatmaydi, shuning uchun davlat kodi.
function CountryCode({ code }) {
    return code ? <span className="lm-flag" aria-hidden="true">{code}</span> : null;
}

function StatTile({ tile, value, active }) {
    const Icon = tile.icon;

    return (
        <div className={`lm-stat lm-tone--${tile.tone}${active ? ' lm-stat--active' : ''}`}>
            <span className="lm-stat-icon" aria-hidden="true">
                <Icon />
            </span>
            <div className="lm-stat-text">
                <p className="lm-stat-label">{tile.label}</p>
                <p className="lm-stat-sub">{tile.sub}</p>
            </div>
            <p className="lm-stat-value"><AnimatedNumber value={value ?? 0} /></p>
            <BumpGlow value={value} />
        </div>
    );
}

function Welcome({ pass }) {
    const foreign = isForeign(pass);
    const organization = pass ? organizationOf(pass) : '';

    return (
        <section className={`lm-welcome${foreign ? ' lm-welcome--foreign' : ''}`} aria-live="polite">
            <p className="lm-welcome-kicker">
                Xush kelibsiz
                <span aria-hidden="true">·</span>
                Welcome
            </p>

            {pass ? (
                <div key={pass.id} className="lm-welcome-body">
                    <Avatar pass={pass} className="lm-hero-avatar" />
                    <h2 className="lm-hero-name">{pass.employee_name}</h2>
                    {organization ? <p className="lm-hero-sub">{organization}</p> : null}
                    <div className="lm-chips">
                        {pass.country_name ? (
                            <span className="lm-chip">
                                <CountryCode code={pass.country} />
                                {pass.country_name}
                            </span>
                        ) : null}
                        {pass.group_name ? (
                            <span className="lm-chip lm-chip--outline">
                                <ApartmentOutlined aria-hidden="true" />
                                {pass.group_name}
                            </span>
                        ) : null}
                        <span className="lm-chip lm-chip--entry">
                            <LoginOutlined aria-hidden="true" />
                            {[pass.device_name, pass.time].filter(Boolean).join(' · ')}
                        </span>
                    </div>
                </div>
            ) : (
                <p className="lm-welcome-empty">
                    Bugun hali kirish qayd etilmagan. Face ID orqali kirgan xodim shu yerda paydo bo‘ladi.
                </p>
            )}
        </section>
    );
}

function RecentRow({ pass, groupTone }) {
    const foreign = isForeign(pass);

    return (
        <li className={`lm-row${foreign ? ' lm-row--foreign' : ''}`}>
            <Avatar pass={pass} className="lm-row-avatar" />
            <div className="lm-row-text">
                <p className="lm-row-name">
                    <span className="lm-row-name-text">{pass.employee_name}</span>
                    {pass.group_name ? (
                        <span className={`lm-tag lm-tone--${groupTone ?? 'blue'}`}>{pass.group_name}</span>
                    ) : null}
                </p>
                <p className="lm-row-sub">{organizationOf(pass) || '—'}</p>
            </div>
            <CountryCode code={pass.country} />
            <span className="lm-row-time">{pass.time}</span>
        </li>
    );
}

// `screen` — login'siz ochiq havola (/screen/{token}): admin panelga qaytish yo‘q, sahifalar shu havolalar bilan.
export default function Index({ passes = [], stats = {}, screen = null }) {
    const { appName } = usePage().props;
    const brandName = appName || 'Biocontrol';
    const [fullscreen, toggleFullscreen] = useFullscreen();

    useInertiaPoll(['passes', 'stats'], POLL_MS);

    const groups = stats.groups ?? [];
    const visibleGroups = groups.slice(0, GROUP_SLOTS);
    const groupTones = new Map(groups.map((group, index) => [group.id, GROUP_TONES[index % GROUP_TONES.length]]));
    const active = activeTileKeys(passes[0]);
    const entered = <StatTile tile={enteredTile} value={stats.entered} active={active.has('entered')} />;

    return (
        <>
            <Head title="Jonli kuzatuv" />

            <div className="live-monitor">
                <header className="lm-header">
                    <div className="lm-brand">
                        <span className="lm-brand-mark" aria-hidden="true">
                            <span>{initialsOf(brandName)}</span>
                        </span>
                        <div>
                            <h1 className="lm-brand-name">{brandName}</h1>
                            <p className="lm-brand-sub">Kirish monitori · Entrance monitor</p>
                        </div>
                    </div>

                    <div className="lm-header-side">
                        <div className="lm-tools">
                            {screen ? null : (
                                <Link
                                    href={route('home.index')}
                                    className="lm-tool"
                                    aria-label="Panelga qaytish"
                                    title="Panelga qaytish"
                                >
                                    <ArrowLeftOutlined aria-hidden="true" />
                                </Link>
                            )}
                            <Link
                                href={screen?.stats_url ?? route('admin.live.stats')}
                                className="lm-tool"
                                aria-label="Tashrif statistikasi"
                                title="Tashrif statistikasi"
                            >
                                <BarChartOutlined aria-hidden="true" />
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
                            JONLI · LIVE
                        </span>
                        <LiveClock />
                    </div>
                </header>

                <section className="lm-stats" aria-label="Bugungi ko‘rsatkichlar">
                    {visibleGroups.length > 0 ? (
                        <div className="lm-stats-row" style={{ '--lm-cols': 1 + visibleGroups.length }}>
                            {entered}
                            {visibleGroups.map((group) => (
                                <StatTile
                                    key={group.id}
                                    tile={{
                                        label: group.name,
                                        sub: 'Bugun kirgan',
                                        tone: groupTones.get(group.id),
                                        icon: ApartmentOutlined,
                                    }}
                                    value={group.entered}
                                    active={active.has(`group-${group.id}`)}
                                />
                            ))}
                        </div>
                    ) : null}
                    {/* Guruh yo‘q bo‘lsa — Kirganlar ham shu qatorda (5 ta plitka), qator chala qolmasin. */}
                    <div className="lm-stats-row" style={{ '--lm-cols': breakdownTiles.length + (visibleGroups.length > 0 ? 0 : 1) }}>
                        {visibleGroups.length > 0 ? null : entered}
                        {breakdownTiles.map((tile) => (
                            <StatTile key={tile.key} tile={tile} value={stats[tile.key]} active={active.has(tile.key)} />
                        ))}
                    </div>
                </section>

                <main className="lm-main">
                    <Welcome pass={passes[0]} />

                    <section className="lm-recent" aria-label="So‘nggi kirganlar">
                        <div className="lm-recent-head">
                            <h2 className="lm-recent-title">So‘nggi kirganlar</h2>
                            <span className="lm-recent-sub">Recent arrivals</span>
                        </div>
                        {passes.length > 0 ? (
                            <ul className="lm-recent-list">
                                {passes.map((pass) => (
                                    <RecentRow key={pass.id} pass={pass} groupTone={groupTones.get(pass.group_id)} />
                                ))}
                            </ul>
                        ) : (
                            <p className="lm-recent-empty">Hozircha ro‘yxat bo‘sh.</p>
                        )}
                    </section>
                </main>
            </div>
        </>
    );
}
