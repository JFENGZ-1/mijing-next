<script setup lang="ts">
import { CopyDocument, PictureFilled, Refresh, UploadFilled, VideoCamera } from "@element-plus/icons-vue";
import { ElMessage, ElMessageBox } from "element-plus";
import { onBeforeUnmount, onMounted, ref } from "vue";

import { ApiError, apiBlob, apiRequest } from "@/api/client";
import PageHeading from "@/components/PageHeading.vue";
import StatusPill from "@/components/StatusPill.vue";

interface MediaAsset {
  id: number;
  uuid: string;
  kind: "image" | "video";
  status: "draft" | "published" | "archived";
  title: string | null;
  originalName: string;
  mimeType: string;
  sizeBytes: number;
  width: number | null;
  height: number | null;
  tenant: { name: string } | null;
  previewPath: string;
  publishedUrl: string | null;
  version: number;
  createdAt: string;
}

interface MediaList {
  items: MediaAsset[];
  pagination: { total: number };
}

const loading = ref(false);
const uploading = ref(false);
const assets = ref<MediaAsset[]>([]);
const total = ref(0);
const kind = ref("");
const status = ref("");
const fileInput = ref<HTMLInputElement>();
const previewUrls = ref<Record<number, string>>({});

function clearPreviews() {
  Object.values(previewUrls.value).forEach((url) => URL.revokeObjectURL(url));
  previewUrls.value = {};
}

async function load() {
  loading.value = true;
  clearPreviews();
  try {
    const params = new URLSearchParams({ perPage: "100" });
    if (kind.value) params.set("kind", kind.value);
    if (status.value) params.set("status", status.value);
    const data = (await apiRequest<MediaList>(`/admin/media-assets?${params}`)).data;
    assets.value = data.items;
    total.value = data.pagination.total;
    await Promise.all(data.items.map(async (asset) => {
      try {
        previewUrls.value[asset.id] = URL.createObjectURL(await apiBlob(asset.previewPath));
      } catch {
        // 缺失文件仍保留元数据卡片，便于管理员发现并处置。
      }
    }));
  } catch (error) {
    ElMessage.error(error instanceof ApiError ? error.payload.message : "资源列表加载失败");
  } finally {
    loading.value = false;
  }
}

async function chooseFile(event: Event) {
  const input = event.target as HTMLInputElement;
  const file = input.files?.[0];
  if (!file) return;
  uploading.value = true;
  try {
    const body = new FormData();
    body.append("file", file);
    body.append("title", file.name.replace(/\.[^.]+$/, ""));
    await apiRequest<MediaAsset>("/admin/media-assets", { method: "POST", body });
    ElMessage.success("资源已上传为草稿，发布后小程序才能访问");
    await load();
  } catch (error) {
    ElMessage.error(error instanceof ApiError ? error.payload.message : "资源上传失败");
  } finally {
    uploading.value = false;
    input.value = "";
  }
}

async function publish(asset: MediaAsset) {
  await ElMessageBox.confirm("发布后将生成无需登录即可访问的稳定 URL，确认发布？", "发布资源", { type: "warning" });
  await apiRequest(`/admin/media-assets/${asset.id}/publish`, { method: "POST", body: JSON.stringify({ version: asset.version }) });
  ElMessage.success("资源已发布");
  await load();
}

async function archive(asset: MediaAsset) {
  await ElMessageBox.confirm("归档后现有公开 URL 会立即失效，但文件仍保留。确认归档？", "归档资源", { type: "warning" });
  await apiRequest(`/admin/media-assets/${asset.id}/archive`, { method: "POST", body: JSON.stringify({ version: asset.version }) });
  ElMessage.success("资源已归档，公开 URL 已关闭");
  await load();
}

async function copyUrl(asset: MediaAsset) {
  if (!asset.publishedUrl) return;
  await navigator.clipboard.writeText(asset.publishedUrl);
  ElMessage.success("已复制发布 URL");
}

function fileSize(bytes: number) {
  if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`;
  return `${(bytes / 1024 / 1024).toFixed(1)} MB`;
}

onMounted(load);
onBeforeUnmount(clearPreviews);
</script>

<template>
  <div class="governance-page" v-loading="loading">
    <PageHeading eyebrow="MEDIA LIBRARY" title="图片与视频资源" description="统一上传、审核和发布平台资源；小程序仅消费已发布 URL，草稿与归档文件不会公开。">
      <el-button :icon="Refresh" @click="load">刷新</el-button>
      <el-button type="primary" :icon="UploadFilled" :loading="uploading" @click="fileInput?.click()">上传资源</el-button>
      <input ref="fileInput" class="hidden-file-input" type="file" accept="image/*,video/mp4,video/webm,video/quicktime" @change="chooseFile" />
    </PageHeading>

    <section class="data-panel media-panel">
      <header class="data-toolbar">
        <el-select v-model="kind" class="status-filter" placeholder="全部类型" clearable @change="load"><el-option label="图片" value="image" /><el-option label="视频" value="video" /></el-select>
        <el-select v-model="status" class="status-filter" placeholder="全部状态" clearable @change="load"><el-option label="草稿" value="draft" /><el-option label="已发布" value="published" /><el-option label="已归档" value="archived" /></el-select>
        <span class="result-count">共 {{ total }} 条真实资源</span>
      </header>

      <div v-if="assets.length" class="media-grid">
        <article v-for="asset in assets" :key="asset.id" class="media-card">
          <div class="media-preview">
            <img v-if="asset.kind === 'image' && previewUrls[asset.id]" :src="previewUrls[asset.id]" :alt="asset.title ?? asset.originalName" />
            <video v-else-if="asset.kind === 'video' && previewUrls[asset.id]" :src="previewUrls[asset.id]" controls preload="metadata" />
            <el-icon v-else><PictureFilled v-if="asset.kind === 'image'" /><VideoCamera v-else /></el-icon>
            <StatusPill :value="asset.status" />
          </div>
          <div class="media-copy">
            <strong>{{ asset.title || asset.originalName }}</strong>
            <span>{{ asset.originalName }}</span>
            <small>{{ fileSize(asset.sizeBytes) }}<template v-if="asset.width"> · {{ asset.width }}×{{ asset.height }}</template> · {{ asset.tenant?.name ?? '平台全局' }}</small>
          </div>
          <footer>
            <el-button v-if="asset.status !== 'published'" size="small" type="primary" @click="publish(asset)">发布</el-button>
            <el-button v-if="asset.publishedUrl" size="small" :icon="CopyDocument" @click="copyUrl(asset)">复制 URL</el-button>
            <el-button v-if="asset.status !== 'archived'" size="small" plain type="danger" @click="archive(asset)">归档</el-button>
          </footer>
        </article>
      </div>
      <el-empty v-else description="暂无资源文件；上传后先保存为草稿" />
    </section>
  </div>
</template>
