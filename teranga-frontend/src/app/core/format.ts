const nombre = new Intl.NumberFormat('fr-FR', { maximumFractionDigits: 2 });
const entier = new Intl.NumberFormat('fr-FR', { maximumFractionDigits: 0 });

export const fmtNumber = (n: unknown): string => (n === null || n === undefined || n === '' ? '—' : nombre.format(Number(n)));

export const fmtMoney = (n: unknown): string => (n === null || n === undefined || n === '' ? '—' : `${entier.format(Number(n))} FCFA`);

/** "2026-11-10" -> "10/11/2026" ; les dates avec heure sont renvoyées telles quelles. */
export const fmtDate = (s: unknown): string => {
  if (!s) return '—';
  const str = String(s);
  if (/^\d{4}-\d{2}-\d{2}$/.test(str)) {
    const [y, m, d] = str.split('-');
    return `${d}/${m}/${y}`;
  }
  if (/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}/.test(str)) {
    const [date, time] = str.split(' ');
    return `${fmtDate(date)} ${time}`;
  }
  return str;
};
