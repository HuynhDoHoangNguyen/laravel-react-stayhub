import { format } from 'date-fns';

/** Format YYYY-MM-DD as dd/MM/yyyy using its calendar date in local time. */
export function formatDate(dateInput) {
  if (!dateInput) return '';

  if (typeof dateInput === 'string') {
    const match = /^(\d{4})-(\d{2})-(\d{2})$/.exec(dateInput);
    if (match) {
      const [, year, month, day] = match;
      return `${day}/${month}/${year}`;
    }
  }

  const date = dateInput instanceof Date ? dateInput : new Date(dateInput);
  if (Number.isNaN(date.getTime())) return String(dateInput);
  return format(date, 'dd/MM/yyyy');
}

