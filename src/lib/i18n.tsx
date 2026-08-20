import { createContext, useCallback, useContext, useEffect, useMemo, useState } from "react";
import type { ReactNode } from "react";

export type Lang = "en" | "om" | "am" | "ti";

export const LANGUAGES: { code: Lang; label: string; short: string }[] = [
  { code: "en", label: "English", short: "EN" },
  { code: "om", label: "Afaan Oromoo", short: "OM" },
  { code: "am", label: "አማርኛ", short: "አማ" },
  { code: "ti", label: "ትግርኛ", short: "ትግ" },
];

type Dict = Record<string, string>;

const en: Dict = {
  "app.name": "Adama 9141",
  "app.subtitle": "Emergency Call Center",
  "nav.dashboard": "Dashboard",
  "nav.newEvent": "New Event",
  "nav.events": "Event List",
  "nav.field": "Field View",
  "nav.followups": "Follow-ups",
  "nav.reports": "Reports & Analytics",
  "nav.performance": "Performance",
  "nav.heatmap": "Heat Map",
  "nav.portal": "Citizen Portal",
  "nav.users": "Users",
  "nav.settings": "Settings",
  "nav.health": "System Health",
  "action.logout": "Logout",
  "action.signIn": "Sign in",
  "action.signingIn": "Signing in…",
  "action.markAllRead": "Mark all read",
  "action.downloadWeekly": "Download weekly report",
  "label.notifications": "Notifications",
  "label.noNotifications": "No notifications",
  "label.offline": "Offline",
  "label.username": "Username",
  "label.password": "Password",
  "label.theme": "Theme",
  "label.language": "Language",
  "label.demoAccounts": "Demo accounts — tap to fill",
  "login.footer": "Track a complaint without signing in via the Citizen Portal after login.",
};

const om: Dict = {
  "app.name": "Adaamaa 9141",
  "app.subtitle": "Wiirtuu Bilbila Balaa",
  "nav.dashboard": "Gabatee",
  "nav.newEvent": "Taatee Haaraa",
  "nav.events": "Tarree Taatee",
  "nav.field": "Ilaalcha Dirree",
  "nav.followups": "Hordoffii",
  "nav.reports": "Gabaasaa fi Xiinxala",
  "nav.performance": "Raawwii",
  "nav.heatmap": "Kaartaa Ho'aa",
  "nav.portal": "Balbala Lammii",
  "nav.users": "Fayyadamtoota",
  "nav.settings": "Qindaa'ina",
  "nav.health": "Fayyaa Sirnaa",
  "action.logout": "Ba'i",
  "action.signIn": "Seeni",
  "action.signingIn": "Seenaa jira…",
  "action.markAllRead": "Hunda dubbifame godhi",
  "action.downloadWeekly": "Gabaasa torbanii buufadhu",
  "label.notifications": "Beeksisawwan",
  "label.noNotifications": "Beeksisni hin jiru",
  "label.offline": "Toora ala",
  "label.username": "Maqaa fayyadamaa",
  "label.password": "Jecha darbii",
  "label.theme": "Bifa",
  "label.language": "Afaan",
  "label.demoAccounts": "Herrega agarsiisaa — guutuuf tuqi",
  "login.footer": "Komii kee balbala lammiitiin hordofuu dandeessa.",
};

const am: Dict = {
  "app.name": "አዳማ 9141",
  "app.subtitle": "የአደጋ ጥሪ ማዕከል",
  "nav.dashboard": "ዳሽቦርድ",
  "nav.newEvent": "አዲስ ክስተት",
  "nav.events": "የክስተት ዝርዝር",
  "nav.field": "የመስክ እይታ",
  "nav.followups": "ክትትሎች",
  "nav.reports": "ሪፖርትና ትንተና",
  "nav.performance": "አፈጻጸም",
  "nav.heatmap": "የሙቀት ካርታ",
  "nav.portal": "የዜጎች መግቢያ",
  "nav.users": "ተጠቃሚዎች",
  "nav.settings": "ቅንብሮች",
  "nav.health": "የሲስተም ጤና",
  "action.logout": "ውጣ",
  "action.signIn": "ግባ",
  "action.signingIn": "በመግባት ላይ…",
  "action.markAllRead": "ሁሉንም እንደተነበበ",
  "action.downloadWeekly": "የሳምንት ሪፖርት አውርድ",
  "label.notifications": "ማሳወቂያዎች",
  "label.noNotifications": "ማሳወቂያ የለም",
  "label.offline": "ከመስመር ውጪ",
  "label.username": "የተጠቃሚ ስም",
  "label.password": "የይለፍ ቃል",
  "label.theme": "ገጽታ",
  "label.language": "ቋንቋ",
  "label.demoAccounts": "የሙከራ መለያዎች — ለመሙላት ይንኩ",
  "login.footer": "ቅሬታዎን በዜጎች መግቢያ በኩል መከታተል ይችላሉ።",
};

const ti: Dict = {
  "app.name": "ኣዳማ 9141",
  "app.subtitle": "ማእከል ጻውዒት ሓደጋ",
  "nav.dashboard": "ዳሽቦርድ",
  "nav.newEvent": "ሓድሽ ፍጻመ",
  "nav.events": "ዝርዝር ፍጻመታት",
  "nav.field": "ትርኢት መቓን",
  "nav.followups": "ክትትላት",
  "nav.reports": "ጸብጻብን ትንተናን",
  "nav.performance": "ኣፈጻጽማ",
  "nav.heatmap": "ካርታ ሙቐት",
  "nav.portal": "መእተዊ ዜጋታት",
  "nav.users": "ተጠቀምቲ",
  "nav.settings": "ቅንብራት",
  "nav.health": "ጥዕና ስርዓት",
  "action.logout": "ውጻእ",
  "action.signIn": "እቶ",
  "action.signingIn": "ይኣቱ ኣሎ…",
  "action.markAllRead": "ኩሉ ከም እተነበበ",
  "action.downloadWeekly": "ሰሙናዊ ጸብጻብ ኣውርድ",
  "label.notifications": "ምልክታታት",
  "label.noNotifications": "ምልክታ የለን",
  "label.offline": "ካብ መስመር ወጻኢ",
  "label.username": "ስም ተጠቃሚ",
  "label.password": "መሕለፊ ቃል",
  "label.theme": "ገጽታ",
  "label.language": "ቋንቋ",
  "label.demoAccounts": "ናይ ሙከራ ሕሳባት — ንምምላእ ተንክፍ",
  "login.footer": "ጥርዓንኩም ብመእተዊ ዜጋታት ክትከታተሉ ትኽእሉ።",
};

const DICTS: Record<Lang, Dict> = { en, om, am, ti };

const KEY = "a9141_lang";

interface I18nCtx {
  lang: Lang;
  setLang: (l: Lang) => void;
  t: (key: string) => string;
}

const Ctx = createContext<I18nCtx>({ lang: "en", setLang: () => {}, t: (k) => en[k] ?? k });

export function I18nProvider({ children }: { children: ReactNode }) {
  const [lang, setLangState] = useState<Lang>("en");

  useEffect(() => {
    const saved = localStorage.getItem(KEY) as Lang | null;
    if (saved && DICTS[saved]) setLangState(saved);
  }, []);

  const setLang = useCallback((l: Lang) => {
    setLangState(l);
    localStorage.setItem(KEY, l);
  }, []);

  const t = useCallback((key: string) => DICTS[lang][key] ?? en[key] ?? key, [lang]);

  const value = useMemo(() => ({ lang, setLang, t }), [lang, setLang, t]);
  return <Ctx.Provider value={value}>{children}</Ctx.Provider>;
}

export const useI18n = () => useContext(Ctx);
