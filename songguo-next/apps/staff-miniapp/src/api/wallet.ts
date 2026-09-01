import { useApiClient } from "@/api/client";
import type {
  MemberWallet,
  MemberWalletAdjustmentInput,
  MemberWalletAdjustmentResult,
} from "@/types/wallet";
import { centsToDecimal, decimalToCents } from "@/utils/money";

interface WalletLedgerWire {
  id: number;
  entryType?: string;
  direction?: "credit" | "debit";
  amount?: string;
  amountCents?: number;
  balanceAfter?: string;
  balanceAfterCents?: number;
  reason?: string | null;
  occurredAt?: string | null;
}

interface MemberWalletWire {
  memberId?: number;
  member_id?: number;
  balance?: string;
  balanceCents?: number;
  balance_cents?: number;
  currency?: string;
  status?: string;
  version?: number;
  updatedAt?: string | null;
  updated_at?: string | null;
  ledgerEntries?: WalletLedgerWire[];
}

function mapWallet(data: MemberWalletWire, fallbackMemberId: number): MemberWallet {
  const balanceCents = data.balanceCents ?? data.balance_cents;
  return {
    memberId: Number(data.memberId ?? data.member_id ?? fallbackMemberId),
    balance: data.balance ?? centsToDecimal(balanceCents) ?? "0.00",
    balanceCents: balanceCents ?? decimalToCents(data.balance) ?? 0,
    currency: data.currency ?? "CNY",
    status: data.status ?? "active",
    version: data.version,
    updatedAt: data.updatedAt ?? data.updated_at,
    ledgerEntries: (data.ledgerEntries ?? []).map((entry) => ({
      id: entry.id,
      entryType: entry.entryType ?? "adjustment",
      direction: entry.direction ?? "credit",
      amount: entry.amount ?? centsToDecimal(entry.amountCents) ?? "0.00",
      balanceAfter: entry.balanceAfter ?? centsToDecimal(entry.balanceAfterCents) ?? "0.00",
      reason: entry.reason,
      occurredAt: entry.occurredAt,
    })),
  };
}

export async function fetchMemberWallet(siteId: number, memberId: number) {
  const response = await useApiClient().request<MemberWalletWire>(
    `/staff/sites/${siteId}/members/${memberId}/wallet`,
  );
  return mapWallet(response.data, memberId);
}

export async function createMemberWalletAdjustment(
  siteId: number,
  memberId: number,
  payload: MemberWalletAdjustmentInput,
) {
  const response = await useApiClient().request<{
    wallet: MemberWalletWire;
    adjustment?: WalletLedgerWire;
    entry?: WalletLedgerWire;
  }>(
    `/staff/sites/${siteId}/members/${memberId}/wallet-adjustments`,
    {
      method: "POST",
      data: {
        amountCents: decimalToCents(payload.amount),
        reason: payload.reason,
        commandKey: payload.commandKey,
        version: payload.version,
      },
    },
  );
  const entry = response.data.adjustment ?? response.data.entry;
  const result: MemberWalletAdjustmentResult = {
    wallet: mapWallet(response.data.wallet, memberId),
  };
  if (entry) {
    result.adjustment = {
      id: entry.id,
      amount: entry.amount ?? centsToDecimal(entry.amountCents) ?? "0.00",
      reason: entry.reason ?? payload.reason,
      occurredAt: entry.occurredAt,
    };
  }
  return result;
}
