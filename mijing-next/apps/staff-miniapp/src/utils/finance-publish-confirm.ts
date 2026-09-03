export interface FinancePublishConfirmationOptions {
  title: string;
  summaryLines: string[];
  warning?: string;
  reasonPlaceholder?: string;
}

const MIN_REASON_LENGTH = 4;
const MAX_SUMMARY_LINES = 8;
const MAX_LINE_LENGTH = 56;

function compactLine(value: string): string {
  const normalized = value.replace(/\s+/g, " ").trim();
  if (normalized.length <= MAX_LINE_LENGTH) return normalized;
  return `${normalized.slice(0, MAX_LINE_LENGTH - 1)}…`;
}

function buildModalContent(options: FinancePublishConfirmationOptions): string {
  const allLines = options.summaryLines.map(compactLine).filter(Boolean);
  const visibleLines = allLines.slice(0, MAX_SUMMARY_LINES);
  if (allLines.length > MAX_SUMMARY_LINES) {
    visibleLines.push(`其余 ${allLines.length - MAX_SUMMARY_LINES} 项变更已折叠`);
  }
  if (options.warning) {
    visibleLines.push(`风险：${compactLine(options.warning)}`);
  }
  visibleLines.push("请核对后填写本次发布的具体原因（至少4个字符）。");
  return visibleLines.join("\n");
}

function showReasonModal(options: FinancePublishConfirmationOptions): Promise<string | null> {
  return new Promise((resolve) => {
    uni.showModal({
      title: options.title,
      content: buildModalContent(options),
      editable: true,
      placeholderText: options.reasonPlaceholder || "例如：九月起调整私教课提成规则",
      confirmText: "确认发布",
      confirmColor: options.warning ? "#c84242" : "#181818",
      success: (result) => resolve(result.confirm ? String(result.content || "").trim() : null),
      fail: () => resolve(null),
    });
  });
}

/**
 * Shows the complete finance change summary and only returns after the operator
 * explicitly confirms it with a meaningful audit reason. No command key should
 * be created before this function resolves with a non-null reason.
 */
export async function confirmFinancePublish(
  options: FinancePublishConfirmationOptions,
): Promise<string | null> {
  while (true) {
    const reason = await showReasonModal(options);
    if (reason === null) return null;
    if (reason.length >= MIN_REASON_LENGTH) return reason;

    uni.showToast({ title: `请输入至少 ${MIN_REASON_LENGTH} 个字符的具体原因`, icon: "none" });
    await new Promise<void>((resolve) => setTimeout(resolve, 700));
  }
}
