export function formatIsoDate(iso: string | null | undefined) {
  if (!iso) return "";
  const date = new Date(iso);
  if (Number.isNaN(date.getTime())) return iso;
  const month = date.getMonth() + 1;
  const day = date.getDate();
  const hours = String(date.getHours()).padStart(2, "0");
  const minutes = String(date.getMinutes()).padStart(2, "0");
  return `${month}月${day}日 ${hours}:${minutes}`;
}

export function todayIsoDate() {
  return new Date().toISOString().slice(0, 10);
}

export function addDaysIsoDate(days: number) {
  const date = new Date();
  date.setDate(date.getDate() + days);
  return date.toISOString().slice(0, 10);
}

export function appointmentStatusLabel(status: string) {
  switch (status) {
    case "confirmed":
      return "已预约";
    case "waitlisted":
      return "排队中";
    case "cancelled":
      return "已取消";
    case "absent":
      return "已旷课";
    case "completed":
      return "已签到";
    default:
      return status;
  }
}

export function cardTypeLabel(cardType: string) {
  return ({ stored_value: "储值卡", count: "计次卡", period: "期限卡" } as Record<string, string>)[cardType] || cardType;
}

export function cardProductSummary(product: {
  cardType: string;
  price: string;
  faceValue?: string | null;
  initialCount?: number | null;
  validityDays?: number | null;
}) {
  const parts = [`¥${product.price}`];
  if (product.faceValue) parts.push(`面值 ¥${product.faceValue}`);
  if (product.initialCount != null) parts.push(`${product.initialCount} 次`);
  if (product.validityDays != null) parts.push(`有效期 ${product.validityDays} 天`);
  return parts.join(" · ");
}

export function cardBalanceLabel(card: {
  cardType: string;
  balance: string | null;
  remainingCount: number | null;
}) {
  if (card.cardType === "count" && card.remainingCount != null) {
    return `剩余 ${card.remainingCount} 次`;
  }
  if (card.cardType === "stored_value" && card.balance) {
    return `余额 ¥${card.balance}`;
  }
  if (card.cardType === "period") {
    return "期限卡";
  }
  return "";
}

export function memberCardStatusLabel(status: string) {
  switch (status) {
    case "pending_activation":
      return "待激活";
    case "active":
      return "正常";
    case "frozen":
      return "已冻结";
    case "expired":
      return "已过期";
    case "exhausted":
      return "已用完";
    case "archived":
      return "已归档";
    case "voided":
      return "已作废";
    default:
      return status;
  }
}

export function memberCardStatusClass(status: string) {
  switch (status) {
    case "active":
      return "status-active";
    case "pending_activation":
      return "status-pending";
    case "frozen":
      return "status-frozen";
    default:
      return "status-muted";
  }
}

export function cardValidityLabel(validFrom: string | null, validUntil: string | null) {
  if (validFrom && validUntil) {
    return `有效期 ${validFrom} 至 ${validUntil}`;
  }
  if (validUntil) {
    return `有效期至 ${validUntil}`;
  }
  if (validFrom) {
    return `自 ${validFrom} 起`;
  }
  return "";
}

export function orderStatusLabel(status: string) {
  switch (status) {
    case "paid":
      return "已支付";
    case "pending_payment":
      return "待支付";
    case "voided":
      return "已作废";
    default:
      return status;
  }
}

export function formatIsoDateOnly(iso: string | null | undefined) {
  if (!iso) return "";
  return iso.slice(0, 10);
}
