<script setup lang="ts">
import { ArrowRight, Lock, User } from "@element-plus/icons-vue";
import { ElMessage } from "element-plus";
import { reactive, ref } from "vue";
import { useRoute, useRouter } from "vue-router";

import { ApiError } from "@/api/client";
import { apiOperations, contractReport } from "@/generated/api-contract";
import { useSessionStore } from "@/stores/session";

const route = useRoute();
const router = useRouter();
const session = useSessionStore();
const submitting = ref(false);
const form = reactive({ account: "", password: "", remember: false });
const adminOperationCount = apiOperations.filter((operation) => operation.path.startsWith("/admin/")).length;

async function submitLogin() {
  if (!form.account.trim() || !form.password) {
    ElMessage.warning("请输入超级管理员账号和密码");
    return;
  }

  submitting.value = true;
  try {
    await session.login(form.account.trim(), form.password, form.remember);
    ElMessage.success("登录成功");
    await router.replace(String(route.query.redirect || "/dashboard"));
  } catch (error) {
    ElMessage.error(error instanceof ApiError ? error.payload.message : "登录服务暂时不可用");
  } finally {
    submitting.value = false;
  }
}
</script>

<template>
  <main class="login-page">
    <section class="login-story">
      <div class="login-brand">
        <div class="brand-mark large"><span>SG</span></div>
        <div><strong>松果超级管理后台</strong><small>SONGGUO PLATFORM CONTROL</small></div>
      </div>
      <div class="story-copy">
        <span class="eyebrow light">PLATFORM OPERATIONS, IN ONE PLACE</span>
        <h1>跨租户管理平台数据<br />每次操作都可控、可追溯。</h1>
        <p>统一管理租户、场馆、会员和平台服务。金额、权益与业务状态均读取服务端权威结果。</p>
      </div>
      <div class="story-grid">
        <article><b>{{ contractReport.total }}</b><span>当前业务接口</span></article>
        <article><b>{{ adminOperationCount }}</b><span>已纳入契约的超管接口</span></article>
        <article><b>8h</b><span>默认会话有效期</span></article>
      </div>
      <div class="story-foot"><i /> 独立超管身份 · 不复用微信或员工账号</div>
    </section>

    <section class="login-panel">
      <div class="login-form-wrap">
        <span class="eyebrow">SUPER ADMIN PORTAL</span>
        <h2>平台管理员登录</h2>
        <p class="login-intro">使用独立的超级管理员账号进入平台后台</p>

        <el-form label-position="top" class="login-form" @submit.prevent="submitLogin">
          <el-form-item label="超级管理员账号">
            <el-input v-model="form.account" size="large" autocomplete="username" placeholder="用户名或邮箱" :prefix-icon="User" @keyup.enter="submitLogin" />
          </el-form-item>
          <el-form-item label="登录密码">
            <el-input v-model="form.password" size="large" type="password" autocomplete="current-password" show-password placeholder="请输入登录密码" :prefix-icon="Lock" @keyup.enter="submitLogin" />
          </el-form-item>
          <div class="login-options"><el-checkbox v-model="form.remember">保持登录</el-checkbox><span>账号由平台运维创建</span></div>
          <el-button class="login-submit" type="primary" size="large" :loading="submitting" @click="submitLogin">
            进入后台 <el-icon><ArrowRight /></el-icon>
          </el-button>
        </el-form>

        <div class="demo-entry admin-auth-note">
          <div><el-icon><Lock /></el-icon></div>
          <p><strong>独立安全域</strong><span>连续失败会触发限流；员工与会员账号无法登录本后台。</span></p>
        </div>

        <p class="login-security">所有超管操作均应进入平台审计记录</p>
      </div>
    </section>
  </main>
</template>
