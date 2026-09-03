export type Identifier = number;

export interface SiteSummary {
  id: Identifier;
  name: string;
  status: "preparing" | "active" | "suspended" | "disabled";
}

export interface ClassSessionSummary {
  id: Identifier;
  courseName: string;
  coachName: string;
  roomName: string;
  startsAt: string;
  endsAt: string;
  capacity: number;
  bookedCount: number;
}

export interface MemberCardSummary {
  id: Identifier;
  name: string;
  type: "times" | "stored_value" | "period" | "hybrid";
  remainingTimes?: number;
  remainingAmountCents?: number;
  expiresAt?: string;
}

export interface ApiEnvelope<T> {
  data: T;
  requestId: string;
}

export interface ApiErrorPayload {
  code: string;
  message: string;
  requestId?: string;
  details?: Record<string, string[]>;
}
