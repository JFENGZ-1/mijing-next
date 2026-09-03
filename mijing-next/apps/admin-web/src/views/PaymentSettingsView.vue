<script setup lang="ts">
import { Lock, Refresh } from "@element-plus/icons-vue";
import { ElMessage, ElMessageBox } from "element-plus";
import { onMounted, reactive, ref } from "vue";

import { ApiError, apiRequest } from "@/api/client";
import PageHeading from "@/components/PageHeading.vue";

interface PaymentConfig {
  exists: boolean;
  source: "environment" | "database";
  enabled: boolean;
  merchantId: string | null;
  merchantSerialNo: string | null;
  platformPublicKeyId: string | null;
  notifyUrl: string | null;
  hasPrivateKey: boolean;
  hasApiV3Key: boolean;
  hasPlatformPublicKey: boolean;
  hasWebhookSecret: boolean;
  configured: boolean;
  version: number;
  updatedAt: string | null;
  updatedBy: { name: string; username: string } | null;
}

const loading = ref(false);
const saving = ref(false);
const current = ref<PaymentConfig | null>(null);
const form = reactive({
  enabled: false,
  merchantId: "",
  merchantSerialNo: "",
  platformPublicKeyId: "",
  notifyUrl: "",
  privateKey: "",
  apiV3Key: "",
  platformPublicKey: "",
  webhookSecret: "",
});

function hydrate(data: PaymentConfig) {
  current.value = data;
  form.enabled = data.enabled;
  form.merchantId = data.merchantId ?? "";
  form.merchantSerialNo = data.merchantSerialNo ?? "";
  form.platformPublicKeyId = data.platformPublicKeyId ?? "";
  form.notifyUrl = data.notifyUrl ?? "";
  form.privateKey = "";
  form.apiV3Key = "";
  form.platformPublicKey = "";
  form.webhookSecret = "";
}

async function load() {
  loading.value = true;
  try {
    hydrate((await apiRequest<PaymentConfig>("/admin/settings/payments/wechat")).data);
  } catch (error) {
    ElMessage.error(error instanceof ApiError ? error.payload.message : "支付配置加载失败");
  } finally {
    loading.value = false;
  }
}

async function save() {
  if (!current.value) return;
  if (form.enabled) {
    await ElMessageBox.confirm("启用后，会员购卡和待支付订单将调用真实微信支付。确认保存当前配置？", "启用真实支付", {
      confirmButtonText: "确认启用",
      cancelButtonText: "取消",
      type: "warning",
    });
  }
  saving.value = true;
  try {
    const payload: Record<string, unknown> = {
      version: current.value.version,
      enabled: form.enabled,
      merchantId: form.merchantId || null,
      merchantSerialNo: form.merchantSerialNo || null,
      platformPublicKeyId: form.platformPublicKeyId || null,
      notifyUrl: form.notifyUrl || null,
    };
    for (const key of ["privateKey", "apiV3Key", "platformPublicKey", "webhookSecret"] as const) {
      if (form[key]) payload[key] = form[key];
    }
    const data = (await apiRequest<PaymentConfig>("/admin/settings/payments/wechat", {
      method: "PUT",
      body: JSON.stringify(payload),
    })).data;
    hydrate(data);
    ElMessage.success("微信支付配置已安全保存");
  } catch (error) {
    if (error !== "cancel") ElMessage.error(error instanceof ApiError ? error.payload.message : "支付配置保存失败");
  } finally {
    saving.value = false;
  }
}

onMounted(load);
</script>

<template>
  <div class="governance-page" v-loading="loading">
    <PageHeading eyebrow="PAYMENT CONTROL" title="微信支付配置" description="平台级密钥只在服务端加密保存并由 PaymentGateway 使用，小程序和浏览器都不能读取已保存的密文。">
      <el-button :icon="Refresh" @click="load">刷新</el-button>
      <el-button type="primary" :loading="saving" @click="save">保存配置</el-button>
    </PageHeading>

    <section class="security-banner">
      <el-icon><Lock /></el-icon>
      <div><strong>密钥单向管理</strong><span>密钥输入后不会再次显示；留空表示保留原值。启用状态会立即影响后续新建的支付请求。</span></div>
      <el-tag :type="current?.configured ? 'success' : 'warning'">{{ current?.configured ? '配置完整' : '配置未完成' }}</el-tag>
    </section>

    <div class="settings-grid">
      <section class="panel settings-form-panel">
        <header class="panel-header"><div><span class="eyebrow">MERCHANT PROFILE</span><h3>商户与回调</h3></div><el-switch v-model="form.enabled" active-text="启用真实支付" /></header>
        <el-form label-position="top" class="governance-form">
          <div class="form-grid two-columns">
            <el-form-item label="微信支付商户号"><el-input v-model="form.merchantId" placeholder="例如 1900000109" /></el-form-item>
            <el-form-item label="商户 API 证书序列号"><el-input v-model="form.merchantSerialNo" /></el-form-item>
            <el-form-item label="微信支付公钥 ID / 平台证书序列号"><el-input v-model="form.platformPublicKeyId" /></el-form-item>
            <el-form-item label="支付结果回调地址"><el-input v-model="form.notifyUrl" placeholder="https://.../webhooks/wechat-pay" /></el-form-item>
          </div>
        </el-form>
      </section>

      <aside class="panel config-facts">
        <header class="panel-header"><div><span class="eyebrow">RUNTIME STATUS</span><h3>运行状态</h3></div></header>
        <div><span>配置来源</span><strong>{{ current?.source === 'database' ? '超管数据库' : '服务器环境变量' }}</strong></div>
        <div><span>当前版本</span><strong>v{{ current?.version ?? 0 }}</strong></div>
        <div><span>最后更新</span><strong>{{ current?.updatedAt ? new Date(current.updatedAt).toLocaleString('zh-CN') : '尚未保存' }}</strong></div>
        <div><span>更新人员</span><strong>{{ current?.updatedBy?.name ?? '—' }}</strong></div>
      </aside>
    </div>

    <section class="panel secret-panel">
      <header class="panel-header"><div><span class="eyebrow">ENCRYPTED CREDENTIALS</span><h3>加密凭据</h3></div><span class="result-count">留空不会覆盖已保存的密钥</span></header>
      <el-form label-position="top" class="governance-form secret-form">
        <div class="form-grid two-columns">
          <el-form-item :label="`商户 API 私钥 · ${current?.hasPrivateKey ? '已配置' : '未配置'}`"><el-input v-model="form.privateKey" type="textarea" :rows="5" placeholder="-----BEGIN PRIVATE KEY-----" /></el-form-item>
          <el-form-item :label="`微信支付平台公钥/证书 · ${current?.hasPlatformPublicKey ? '已配置' : '未配置'}`"><el-input v-model="form.platformPublicKey" type="textarea" :rows="5" placeholder="-----BEGIN PUBLIC KEY-----" /></el-form-item>
          <el-form-item :label="`APIv3 密钥 · ${current?.hasApiV3Key ? '已配置' : '未配置'}`"><el-input v-model="form.apiV3Key" type="password" show-password maxlength="32" placeholder="必须为 32 位" /></el-form-item>
          <el-form-item :label="`本地联调回调密钥 · ${current?.hasWebhookSecret ? '已配置' : '未配置'}`"><el-input v-model="form.webhookSecret" type="password" show-password placeholder="仅本地联调使用，至少 32 位" /></el-form-item>
        </div>
      </el-form>
    </section>
  </div>
</template>
