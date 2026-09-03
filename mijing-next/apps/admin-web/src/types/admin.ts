export type ResourceKey =
  | "members"
  | "courses"
  | "schedules"
  | "appointments"
  | "cards"
  | "orders"
  | "sites"
  | "staff"
  | "reports"
  | "audit";

export type OperationDisposition = "ADOPT" | "CUSTOM" | "IGNORE" | "UNCLASSIFIED";

export interface ResourceColumn {
  key: string;
  label: string;
  width?: number;
  minWidth?: number;
  type?: "text" | "money" | "status" | "date" | "number";
}

export interface ResourceAction {
  key: string;
  label: string;
  permission?: string;
  danger?: boolean;
}

export interface ResourceDefinition {
  key: ResourceKey;
  title: string;
  eyebrow: string;
  description: string;
  permission: string;
  scope: "platform" | "tenant" | "site";
  primaryOperation?: string;
  operations: string[];
  columns: ResourceColumn[];
  actions: ResourceAction[];
}

export interface ApiOperationSummary {
  operationId: string;
  method: string;
  path: string;
  group: string;
  disposition: OperationDisposition;
}

export interface ContractReport {
  generatedAt: string;
  sourceHash: string;
  total: number;
  counts: Record<OperationDisposition, number>;
  changes: {
    added: string[];
    changed: string[];
    removed: string[];
    sourceChanged: boolean;
  };
}
