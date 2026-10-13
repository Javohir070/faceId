import '../../../../css/live-monitor.css';

import { Head, Link, usePage } from '@inertiajs/react';
import {
    ApartmentOutlined,
    ArrowLeftOutlined,
    BankOutlined,
    FullscreenExitOutlined,
    FullscreenOutlined,
    LoginOutlined,
    LogoutOutlined,
    ManOutlined,
    WomanOutlined,
} from '@ant-design/icons';
import { useEffect, useState } from 'react';
import useInertiaPoll from '../../../hooks/useInertiaPoll';
import { route } from '../../../routes';

const POLL_MS = 3000;
const TYPE_EXIT = 2;

const WEEKDAYS = ['Yakshanba', 'Dushanba', 'Seshanba', 'Chorshanba', 'Payshanba', 'Juma', 'Shanba'];
const MONTHS = [
    'yanvar', 'fevral', 'mart', 'aprel', 'may', 'iyun',
    'iyul', 'avgust', 'sentabr', 'oktabr', 'noyabr', 'dekabr',
];

const statTiles = [
    { key: 'entered', label: 'Kirganlar', sub: 'Entered today', tone: 'green', icon: LoginOutlined },
    { key: 'exited', label: 'Chiqqanlar', sub: 'Exited today', tone: 'yellow', icon: LogoutOutlined },
    { key: 'male', label: 'Erkaklar', sub: 'Male', tone: 'blue', icon: ManOutlined },
    { key: 'female', label: 'Ayollar', sub: 'Female', tone: 'pink', icon: WomanOutlined },
];

function initialsOf(name) {
    return String(name || '')
        .trim()
        .split(/\s+/)
        .slice(0, 2)
        .map((word) => word.charAt(0).toUpperCase())
        .join('') || '?';
}

function formatNumber(value) {
    return String(value ?? 0).replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
}

function useNow() {
    const [now, setNow] = useState(() => new Date());

    useEffect(() => {
        const id = window.setInterval(() => setNow(new Date()), 1000);

        return () => window.clearInterval(id);
    }, []);

    return now;
}

function useFullscreen() {
    const [active, setActive] = useState(false);

    useEffect(() => {
        const onChange = () => setActive(Boolean(document.fullscreenElement));

        document.addEventListener('fullscreenchange', onChange);

        return () => document.removeEventListener('fullscreenchange', onChange);
    }, []);

    const toggle = () => {
        if (document.fullscreenElement) {
            document.exitFullscreen?.();
        } else {
            document.documentElement.requestFullscreen?.().catch(() => {});
        }
    };

    return [active, toggle];
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

function Clock() {
    const now = useNow();
    const time = `${String(now.getHours()).padStart(2, '0')}:${String(now.getMinutes()).padStart(2, '0')}`;
    const date = `${now.getDate()}-${MONTHS[now.getMonth()]}, ${now.getFullYear()} · ${WEEKDAYS[now.getDay()]}`;

    return (
        <div className="lm-clock">
            <p className="lm-clock-time">{time}</p>
            <p className="lm-clock-date">{date}</p>
        </div>
    );
}

function StatTile({ tile, value }) {
    const Icon = tile.icon;

    return (
        <div className={`lm-stat lm-stat--${tile.tone}`}>
            <span className="lm-stat-icon" aria-hidden="true">
                <Icon />
            </span>
            <div className="lm-stat-text">
                <p className="lm-stat-label">{tile.label}</p>
                <p className="lm-stat-sub">{tile.sub}</p>
            </div>
            <p className="lm-stat-value">{formatNumber(value)}</p>
        </div>
    );
}

function TypeIcon({ pass }) {
    return pass.type === TYPE_EXIT
        ? <LogoutOutlined aria-hidden="true" />
        : <LoginOutlined aria-hidden="true" />;
}

function Welcome({ pass }) {
    const isExit = pass?.type === TYPE_EXIT;

    return (
        <section className={`lm-welcome${isExit ? ' lm-welcome--exit' : ''}`} aria-live="polite">
            <p className="lm-welcome-kicker">
                {isExit ? 'Xayr' : 'Xush kelibsiz'}
                <span aria-hidden="true">·</span>
                {isExit ? 'Goodbye' : 'Welcome'}
            </p>

            {pass ? (
                <div key={pass.id} className="lm-welcome-body">
                    <Avatar pass={pass} className="lm-hero-avatar" />
                    <h2 className="lm-hero-name">{pass.employee_name}</h2>
                    {pass.position ? <p className="lm-hero-sub">{pass.position}</p> : null}
                    <div className="lm-chips">
                        {pass.department_name ? (
                            <span className="lm-chip">
                                <BankOutlined aria-hidden="true" />
                                {pass.department_name}
                            </span>
                        ) : null}
                        {pass.group_name ? (
                            <span className="lm-chip lm-chip--outline">
                                <ApartmentOutlined aria-hidden="true" />
                                {pass.group_name}
                            </span>
                        ) : null}
                        <span className={`lm-chip ${isExit ? 'lm-chip--exit' : 'lm-chip--entry'}`}>
                            <TypeIcon pass={pass} />
                            {[pass.type_label, pass.device_name, pass.time].filter(Boolean).join(' · ')}
                        </span>
                    </div>
                </div>
            ) : (
                <p className="lm-welcome-empty">
                    Bugun hali o‘tish qayd etilmagan. Face ID orqali o‘tgan xodim shu yerda paydo bo‘ladi.
                </p>
            )}
        </section>
    );
}

function RecentRow({ pass, latest }) {
    return (
        <li className={`lm-row${latest ? ' lm-row--latest' : ''}`}>
            <Avatar pass={pass} className="lm-row-avatar" />
            <div className="lm-row-text">
                <p className="lm-row-name">{pass.employee_name}</p>
                <p className="lm-row-sub">
                    {[pass.position, pass.department_name, pass.device_name].filter(Boolean).join(' · ') || '—'}
                </p>
            </div>
            <span className={`lm-row-type ${pass.type === TYPE_EXIT ? 'lm-row-type--exit' : 'lm-row-type--entry'}`}>
                <TypeIcon pass={pass} />
                {pass.type_label}
            </span>
            <span className="lm-row-time">{pass.time}</span>
        </li>
    );
}

export default function Index({ passes = [], stats = {} }) {
    const { appName } = usePage().props;
    const brandName = appName || 'Biocontrol';
    const [fullscreen, toggleFullscreen] = useFullscreen();

    useInertiaPoll(['passes', 'stats'], POLL_MS);

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
                            <Link
                                href={route('home.index')}
                                className="lm-tool"
                                aria-label="Panelga qaytish"
                                title="Panelga qaytish"
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
                            JONLI · LIVE
                        </span>
                        <Clock />
                    </div>
                </header>

                <section className="lm-stats" aria-label="Bugungi ko‘rsatkichlar">
                    {statTiles.map((tile) => (
                        <StatTile key={tile.key} tile={tile} value={stats[tile.key]} />
                    ))}
                </section>

                <main className="lm-main">
                    <Welcome pass={passes[0]} />

                    <section className="lm-recent" aria-label="So‘nggi o‘tganlar">
                        <div className="lm-recent-head">
                            <h2 className="lm-recent-title">So‘nggi o‘tganlar</h2>
                            <span className="lm-recent-sub">Recent passes</span>
                        </div>
                        {passes.length > 0 ? (
                            <ul className="lm-recent-list">
                                {passes.map((pass, index) => (
                                    <RecentRow key={pass.id} pass={pass} latest={index === 0} />
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
