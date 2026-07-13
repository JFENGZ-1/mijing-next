const weekdayLabels = ["日", "一", "二", "三", "四", "五", "六"];

export function todayIsoDate() {
  const date = new Date();
  return formatLocalIsoDate(date);
}

export function addDaysIsoDate(baseIso: string, days: number) {
  const date = parseIsoDate(baseIso);
  date.setDate(date.getDate() + days);
  return formatLocalIsoDate(date);
}

export function buildWeekDates(startIso = todayIsoDate(), count = 7) {
  return Array.from({ length: count }, (_, index) => {
    const value = addDaysIsoDate(startIso, index);
    const date = parseIsoDate(value);
    const month = date.getMonth() + 1;
    const day = date.getDate();
    let label = `${month}/${day}`;
    if (index === 0) label = "今天";
    else if (index === 1) label = "明天";
    else label = `周${weekdayLabels[date.getDay()]}`;
    return { value, label, subLabel: `${month}/${day}` };
  });
}

export function formatSessionTime(startsAt?: string | null, endsAt?: string | null) {
  if (!startsAt) return "";
  const start = formatClock(startsAt);
  const end = endsAt ? formatClock(endsAt) : "";
  return end ? `${start}-${end}` : start;
}

export function formatClock(iso: string) {
  const date = new Date(iso);
  if (Number.isNaN(date.getTime())) return "";
  return `${String(date.getHours()).padStart(2, "0")}:${String(date.getMinutes()).padStart(2, "0")}`;
}

export function splitLocalDateTime(iso: string) {
  const date = new Date(iso);
  return {
    date: formatLocalIsoDate(date),
    time: formatClock(iso),
  };
}

export function combineLocalDateTime(date: string, time: string) {
  const [year, month, day] = date.split("-").map(Number);
  const [hour, minute] = time.split(":").map(Number);
  return new Date(year, month - 1, day, hour, minute, 0).toISOString();
}

export function sessionStatusLabel(status: string) {
  switch (status) {
    case "scheduled":
      return "待上课";
    case "suspended":
      return "已停课";
    case "cancelled":
      return "已取消";
    case "completed":
      return "已结束";
    default:
      return status;
  }
}

export function sessionStatusType(status: string): "success" | "warning" | "error" | "info" {
  switch (status) {
    case "scheduled":
      return "success";
    case "suspended":
      return "warning";
    case "cancelled":
      return "error";
    case "completed":
      return "info";
    default:
      return "info";
  }
}

export function appointmentStatusLabel(status: string) {
  switch (status) {
    case "confirmed":
      return "已预约";
    case "waitlisted":
      return "候补中";
    case "cancelled":
      return "已取消";
    case "absent":
      return "缺席";
    case "completed":
      return "已完成";
    default:
      return status;
  }
}

function parseIsoDate(iso: string) {
  const [year, month, day] = iso.split("-").map(Number);
  return new Date(year, month - 1, day);
}

function formatLocalIsoDate(date: Date) {
  const year = date.getFullYear();
  const month = String(date.getMonth() + 1).padStart(2, "0");
  const day = String(date.getDate()).padStart(2, "0");
  return `${year}-${month}-${day}`;
}
