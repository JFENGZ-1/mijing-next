export interface MemberWallet {
  memberId: number;
  balance: string;
  balanceCents?: number;
  currency?: string;
  status?: string;
  version?: number;
  updatedAt?: string | null;
  ledgerEntries?: Array<{
    id: number;
    entryType: string;
    direction: "credit" | "debit";
    amount: string;
    balanceAfter: string;
    reason?: string | null;
    occurredAt?: string | null;
  }>;
}

export interface MemberWalletAdjustmentInput {
  amount: string;
  reason: string;
  commandKey: string;
  version?: number;
}

export interface MemberWalletAdjustmentResult {
  wallet: MemberWallet;
  adjustment?: {
    id: number;
    amount: string;
    reason: string;
    occurredAt?: string | null;
  };
}
