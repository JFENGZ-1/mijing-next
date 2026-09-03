<script setup lang="ts">
import { computed, reactive, ref } from "vue";
import { onLoad, onShow } from "@dcloudio/uni-app";
import { requireStaffAuth } from "@/auth/guard";
import {
  createCrmMember,
  fetchCrmMember,
  fetchCrmSalesStaff,
  transitionCrmMemberStatus,
  updateCrmMember,
  updateCrmStickyRemark,
} from "@/api/crm";
import { fetchCrmMemberFieldPolicy } from "@/api/settings";
import { useSessionStore } from "@/stores/session";
import type { CrmMemberFieldPolicyItem } from "@/types/settings";
import type { CrmSalesStaff } from "@/types/crm";
import CustomNav from "@/components/custom-nav/custom-nav.vue";
import FfBottomLogo from "@/components/ff-bottom-logo/ff-bottom-logo.vue";
import MemberRemarkSheet from "@/components/member-remark-sheet/member-remark-sheet.vue";

const session = useSessionStore();
const memberId = ref<number>();
const loading = ref(false);
const saving = ref(false);
const loaded = ref(false);
const errorMessage = ref("");
const version = ref<number>();
const avatarUrl = ref("");
const fieldPolicy = ref<CrmMemberFieldPolicyItem[]>([]);
const showSexPicker = ref(false);
const showRemarkEditor = ref(false);
const showConsultantPicker = ref(false);
const consultants = ref<CrmSalesStaff[]>([]);
const nationalIdMasked = ref("");
const form = reactive({
  name: "",
  mobile: "",
  gender: "" as "" | "male" | "female" | "undisclosed",
  birthDate: "",
  stickyRemark: "",
  assignToMe: true,
  idCard: "",
  height: "",
  weight: "",
  consultantId: undefined as number | undefined,
  consultantName: "",
});

const sexOptions = ["女", "男"] as const;
const navTitle = computed(() => (memberId.value ? "会员信息" : "添加会员"));
const canBatchImport = computed(
  () => !memberId.value && (session.can("crm.member.batch-import") || session.can("crm.member.create")),
);

function policyOf(key: string) {
  return fieldPolicy.value.find((item) => item.key === key);
}

function isVisible(key: string) {
  const item = policyOf(key);
  if (!item) return true;
  return item.isVisible;
}

function isRequired(key: string) {
  return !!policyOf(key)?.isRequired;
}

function isEditable(key: string) {
  const item = policyOf(key);
  if (!item) return true;
  return item.staffEditable !== false;
}

function genderDisplay() {
  if (form.gender === "male") return "男";
  if (form.gender === "female") return "女";
  return "请选择";
}

function openBatchImport() {
  uni.navigateTo({ url: "/subpackages/members/batch-import" });
}

function openSexPicker() {
  if (!isEditable("gender")) return;
  showSexPicker.value = true;
}

function onSexConfirm(e: { indexs?: number[]; value?: string[] } | number) {
  const idx = typeof e === "number" ? e : (e.indexs?.[0] ?? 0);
  form.gender = idx === 1 ? "male" : "female";
  showSexPicker.value = false;
}

function onSexSheetSelect(item: { name: string }) {
  onSexConfirm(item.name === "男" ? 1 : 0);
}

function openRemark() {
  showRemarkEditor.value = true;
}

function onRemarkConfirm(text: string) {
  form.stickyRemark = text.trim();
  showRemarkEditor.value = false;
}

async function loadFieldPolicy() {
  if (!session.currentSiteId) return;
  try {
    const policy = await fetchCrmMemberFieldPolicy(session.currentSiteId);
    fieldPolicy.value = policy.fields;
  } catch {
    fieldPolicy.value = [];
  }
}

async function loadConsultants() {
  if (!session.currentSiteId || !session.can("crm.member.read")) return;
  try {
    const response = await fetchCrmSalesStaff(session.currentSiteId);
    consultants.value = response.data.items;
  } catch {
    consultants.value = [];
  }
}

function openConsultantPicker() {
  if (!isEditable("ownerStaffId") || consultants.value.length === 0) return;
  showConsultantPicker.value = true;
}

function onConsultantSelect(item: { id?: number; name: string }) {
  const selected = consultants.value.find((consultant) => consultant.id === item.id || consultant.name === item.name);
  if (!selected) return;
  form.consultantId = selected.id;
  form.consultantName = selected.name;
  form.assignToMe = false;
  showConsultantPicker.value = false;
}

async function load() {
  if (!memberId.value || !session.currentSiteId) return;
  loading.value = true;
  try {
    const response = await fetchCrmMember(session.currentSiteId, memberId.value);
    form.name = response.data.name;
    form.gender = response.data.gender ?? "";
    form.birthDate = response.data.birthDate ?? "";
    nationalIdMasked.value = response.data.nationalIdMasked ?? "";
    form.idCard = "";
    form.height = response.data.heightCm === null ? "" : String(response.data.heightCm);
    form.weight = response.data.weightKg === null ? "" : String(response.data.weightKg);
    form.consultantId = response.data.owner?.id;
    form.consultantName = response.data.owner?.name ?? "";
    form.assignToMe = false;
    form.stickyRemark = response.data.stickyRemark || "";
    avatarUrl.value = response.data.avatarUrl || "";
    version.value = response.data.version;
    loaded.value = true;
  } catch (error) {
    errorMessage.value = error instanceof Error ? error.message : "资料加载失败";
  } finally {
    loading.value = false;
  }
}

function validate(): string | null {
  if (isVisible("name") && isRequired("name") && !form.name.trim()) return "请填写会员姓名";
  if (!form.name.trim()) return "请填写会员姓名";
  if (isVisible("mobile") && isRequired("mobile") && !form.mobile.trim() && !memberId.value) {
    return "请填写手机号";
  }
  if (isVisible("gender") && isRequired("gender") && !form.gender) return "请选择性别";
  if (isVisible("birthDate") && isRequired("birthDate") && !form.birthDate) return "请选择生日";
  if (isVisible("nationalId") && isRequired("nationalId") && !form.idCard.trim() && !nationalIdMasked.value) {
    return "请填写身份证";
  }
  if (isVisible("heightCm") && isRequired("heightCm") && !form.height) return "请填写身高";
  if (isVisible("weightKg") && isRequired("weightKg") && !form.weight) return "请填写体重";
  if (isVisible("ownerStaffId") && isRequired("ownerStaffId") && !form.consultantId && !form.assignToMe) {
    return "请选择会籍顾问";
  }
  return null;
}

async function save() {
  if (!session.currentSiteId) return;
  const invalid = validate();
  if (invalid) {
    errorMessage.value = invalid;
    uni.showToast({ title: invalid, icon: "none" });
    return;
  }
  saving.value = true;
  errorMessage.value = "";
  try {
    if (memberId.value) {
      const response = await updateCrmMember(session.currentSiteId, memberId.value, {
        version: version.value!,
        name: form.name.trim(),
        gender: isVisible("gender") ? (form.gender || null) : undefined,
        birthDate: isVisible("birthDate") ? (form.birthDate || null) : undefined,
        heightCm: isVisible("heightCm") ? (form.height ? Number(form.height) : null) : undefined,
        weightKg: isVisible("weightKg") ? (form.weight ? Number(form.weight) : null) : undefined,
        ownerStaffId: isVisible("ownerStaffId") ? (form.consultantId ?? null) : undefined,
        ...(form.mobile.trim() ? { mobile: form.mobile.trim() } : {}),
        ...(form.idCard.trim() ? { nationalId: form.idCard.trim() } : {}),
      });
      if (form.stickyRemark.trim() !== (response.data.stickyRemark || "")) {
        await updateCrmStickyRemark(
          session.currentSiteId,
          memberId.value,
          response.data.version,
          form.stickyRemark.trim() || null,
        );
      }
    } else {
      const created = await createCrmMember(session.currentSiteId, {
        name: form.name.trim(),
        mobile: form.mobile.trim() || null,
        gender: isVisible("gender") ? (form.gender || null) : null,
        birthDate: isVisible("birthDate") ? (form.birthDate || null) : null,
        nationalId: isVisible("nationalId") ? (form.idCard.trim() || null) : null,
        heightCm: isVisible("heightCm") ? (form.height ? Number(form.height) : null) : null,
        weightKg: isVisible("weightKg") ? (form.weight ? Number(form.weight) : null) : null,
        ...(form.consultantId ? { ownerStaffId: form.consultantId } : {}),
        assignToMe: form.assignToMe,
      });
      if (form.stickyRemark.trim()) {
        await updateCrmStickyRemark(
          session.currentSiteId,
          created.data.id,
          created.data.version,
          form.stickyRemark.trim(),
        );
      }
    }
    uni.navigateBack();
  } catch (error) {
    errorMessage.value = error instanceof Error ? error.message : "保存失败";
    uni.showToast({ title: errorMessage.value, icon: "none" });
  } finally {
    saving.value = false;
  }
}

function confirmDelete() {
  uni.showModal({
    title: "警告：确认删除此会员吗？",
    content: "会员将移入已删除列表，历史约课和会员卡记录会保留，之后可恢复。",
    confirmText: "确定",
    cancelText: "取消",
    success: async (res) => {
      if (!res.confirm || !session.currentSiteId || !memberId.value || !version.value) return;
      try {
        await transitionCrmMemberStatus(session.currentSiteId, memberId.value, {
          version: version.value,
          targetStatus: "closed",
          reason: "员工端归档会员",
        });
        uni.showToast({ title: "已移入删除列表", icon: "success" });
        setTimeout(() => uni.navigateBack(), 500);
      } catch (error) {
        uni.showToast({ title: error instanceof Error ? error.message : "删除失败", icon: "none" });
      }
    },
  });
}

onLoad((options) => {
  if (options?.id) memberId.value = Number(options.id);
});

onShow(async () => {
  if (!(await requireStaffAuth())) return;
  await loadFieldPolicy();
  await loadConsultants();
  if (memberId.value && !loaded.value) await load();
});
</script>

<template>
  <view class="page">
    <CustomNav :text="navTitle" bg="#FFFFFF" />
    <u-loading-page :loading="loading" />
    <view v-if="!loading" class="main">
      <view v-if="!memberId && canBatchImport" class="top">
        <text class="batch" @tap="openBatchImport">批量录入</text>
      </view>
      <view v-else class="headle" />

      <u-alert v-if="errorMessage" type="error" :description="errorMessage" />

      <view class="portrait">
        <view :class="memberId || avatarUrl ? 'head_portrait' : 'head_portraits'">
          <image v-if="avatarUrl" class="u-avatar-demo" :src="avatarUrl" mode="aspectFill" />
          <u-icon v-else name="account-fill" size="40" color="#989898" />
        </view>
      </view>

      <view class="information">
        <view v-if="isVisible('name')" class="content-grid">
          <view class="grid">
            <text class="grid-label">姓名</text>
            <text v-if="isRequired('name')" class="required">*</text>
          </view>
          <u-input
            v-model="form.name"
            maxlength="80"
            :placeholder="form.name ? '' : '请输入姓名'"
            :disabled="!isEditable('name')"
            border="none"
            input-align="left"
            font-size="30rpx"
            color="#7e7e7e"
            custom-style="flex:5;"
          />
        </view>

        <view v-if="isVisible('mobile')" class="content-grid">
          <view class="grid">
            <text class="grid-label">手机</text>
            <text v-if="isRequired('mobile') && !memberId" class="required">*</text>
          </view>
          <u-input
            v-model="form.mobile"
            maxlength="24"
            :placeholder="memberId ? (form.mobile ? '' : '留空不修改') : (form.mobile ? '' : '请输入手机号')"
            :disabled="!isEditable('mobile')"
            border="none"
            input-align="left"
            font-size="30rpx"
            color="#7e7e7e"
            custom-style="flex:5;"
          />
        </view>

        <view v-if="isVisible('gender')" class="content-sex" @tap="openSexPicker">
          <view class="grid">
            <text class="grid-label">性别</text>
            <text v-if="isRequired('gender')" class="required">*</text>
          </view>
          <view :class="form.gender ? 'sex' : 'sl-placeholder'">{{ genderDisplay() }}</view>
          <u-icon name="arrow-right" size="12" color="#dadada" />
        </view>

        <view v-if="isVisible('birthDate')" class="content-sex">
          <view class="grid">
            <text class="grid-label">生日</text>
            <text v-if="isRequired('birthDate')" class="required">*</text>
          </view>
          <picker
            mode="date"
            :disabled="!isEditable('birthDate')"
            :value="form.birthDate"
            :end="new Date().toISOString().slice(0, 10)"
            @change="form.birthDate = String($event.detail.value)"
          >
            <view :class="form.birthDate ? 'sex' : 'sl-placeholder'">
              {{ form.birthDate || "请选择" }}
            </view>
          </picker>
          <u-icon name="arrow-right" size="12" color="#dadada" />
        </view>

        <view v-if="isVisible('nationalId')" class="content-grid">
          <view class="grid">
            <text class="grid-label">身份证</text>
            <text v-if="isRequired('nationalId')" class="required">*</text>
          </view>
          <u-input
            v-model="form.idCard"
            maxlength="32"
            :placeholder="nationalIdMasked ? `${nationalIdMasked}（留空不修改）` : '请输入身份证'"
            :disabled="!isEditable('nationalId')"
            border="none"
            input-align="left"
            font-size="30rpx"
            color="#7e7e7e"
            custom-style="flex:5;"
          />
        </view>

        <view v-if="isVisible('heightCm')" class="content-grid">
          <view class="grid">
            <text class="grid-label">身高</text>
            <text v-if="isRequired('heightCm')" class="required">*</text>
          </view>
          <u-input
            v-model="form.height"
            type="digit"
            maxlength="6"
            placeholder="请输入身高"
            :disabled="!isEditable('heightCm')"
            border="none"
            input-align="left"
            font-size="30rpx"
            color="#7e7e7e"
            custom-style="flex:5;"
          />
          <text class="unit">cm</text>
        </view>

        <view v-if="isVisible('weightKg')" class="content-grid">
          <view class="grid">
            <text class="grid-label">体重</text>
            <text v-if="isRequired('weightKg')" class="required">*</text>
          </view>
          <u-input
            v-model="form.weight"
            type="digit"
            maxlength="6"
            placeholder="请输入体重"
            :disabled="!isEditable('weightKg')"
            border="none"
            input-align="left"
            font-size="30rpx"
            color="#7e7e7e"
            custom-style="flex:5;"
          />
          <text class="unit">kg</text>
        </view>

        <view v-if="isVisible('ownerStaffId')" class="content-sex" @tap="openConsultantPicker">
          <view class="grid">
            <text class="grid-label">会籍</text>
            <text v-if="isRequired('ownerStaffId')" class="required">*</text>
          </view>
          <view :class="form.consultantName ? 'sex' : 'sl-placeholder'">
            {{ form.consultantName || "请选择会籍顾问" }}
          </view>
          <u-icon name="arrow-right" size="12" color="#dadada" />
        </view>

        <view class="content-sex" @tap="openRemark">
          <view class="grid">
            <text class="grid-label">备注</text>
          </view>
          <view :class="form.stickyRemark ? 'sex remake' : 'sl-placeholder'">
            {{ form.stickyRemark || "无" }}
          </view>
          <u-icon name="arrow-right" size="12" color="#dadada" />
        </view>

        <view v-if="!memberId" class="owner-row" @tap="form.assignToMe = !form.assignToMe">
          <view :class="form.assignToMe ? 'checkboxs' : 'checkbox'" />
          <text>创建后由我负责跟进</text>
        </view>

        <view class="btns">
          <view class="preservation" :class="{ disabled: saving }" @tap="save">
            {{ saving ? "保存中..." : "保存" }}
          </view>
          <view v-if="memberId && session.can('crm.member.status.manage')" class="delete" @tap="confirmDelete">
            删除
          </view>
        </view>
      </view>

      <FfBottomLogo />
    </view>

    <u-action-sheet
      :show="showSexPicker"
      :actions="sexOptions.map((label) => ({ name: label }))"
      title="选择性别"
      @close="showSexPicker = false"
      @select="onSexSheetSelect"
    />

    <u-action-sheet
      :show="showConsultantPicker"
      :actions="consultants.map((item) => ({ id: item.id, name: item.name }))"
      title="选择会籍顾问"
      @close="showConsultantPicker = false"
      @select="onConsultantSelect"
    />

    <MemberRemarkSheet
      v-model:show="showRemarkEditor"
      :value="form.stickyRemark"
      @confirm="onRemarkConfirm"
    />
  </view>
</template>

<style scoped lang="scss">
.page {
  min-height: 100vh;
  background: #fff;
}
.main {
  background: #fff;
  min-height: 1230rpx;
  padding-top: calc(var(--status-bar-height, 20px) + 44px);
}
.top {
  align-items: center;
  display: flex;
  height: 55rpx;
  justify-content: flex-end;
  margin-right: 44rpx;
  margin-top: 24rpx;
}
.batch {
  color: #181818;
  font-size: 28rpx;
  padding-right: 8rpx;
}
.headle {
  height: 51rpx;
}
.portrait {
  display: flex;
  flex-direction: column;
  margin: 0 auto;
  width: 150rpx;
}
.head_portrait,
.head_portraits {
  border-radius: 50%;
  height: 141rpx;
  margin: auto;
  width: 141rpx;
  overflow: hidden;
}
.head_portrait .u-avatar-demo {
  border-radius: 50%;
  height: 100%;
  width: 100%;
}
.head_portraits {
  align-items: center;
  background: #e7e7e7;
  display: flex;
  justify-content: center;
}
.imghead_text {
  color: #003d82;
  font-size: 22rpx;
  line-height: 24rpx;
  margin-top: 18rpx;
  text-align: center;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 6rpx;
}
.information {
  padding: 30rpx 38rpx 0 36rpx;
}
.content-grid,
.content-sex {
  align-items: center;
  display: flex;
  justify-content: space-between;
  padding-bottom: 25rpx;
  padding-top: 25rpx;
  position: relative;
}
.content-grid::after,
.content-sex::after {
  border-bottom: 1px solid #f0f0f0;
  box-sizing: border-box;
  content: " ";
  height: 200%;
  left: -50%;
  pointer-events: none;
  position: absolute;
  top: -50%;
  transform: scale(0.5);
  width: 200%;
  z-index: 2;
}
.grid {
  width: 105rpx;
  flex-shrink: 0;
}
.grid-label {
  color: #181818;
  font-weight: 400;
  font-size: 28rpx;
}
.required {
  color: #dc3c5c;
  padding-left: 3rpx;
}
.unit {
  color: #7e7e7e;
  font-size: 26rpx;
  margin-left: 12rpx;
}
.sex,
.sl-placeholder {
  flex: 1;
  height: 50rpx;
  line-height: 50rpx;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  font-size: 30rpx;
}
.sex {
  color: #7e7e7e;
}
.sl-placeholder {
  color: #d0d2d7;
}
.remake {
  margin-right: 20rpx;
}
.owner-row {
  display: flex;
  align-items: center;
  margin-top: 28rpx;
  font-size: 26rpx;
  color: #181818;
  gap: 12rpx;
}
.checkbox,
.checkboxs {
  border-radius: 50%;
  height: 31rpx;
  width: 31rpx;
}
.checkbox {
  background: #dadada;
}
.checkboxs {
  background: #00cb83;
}
.btns {
  padding-bottom: 50rpx;
  padding-top: 85rpx;
}
.preservation {
  background: #fbd128;
  border-radius: 41px;
  color: #181818;
  font-size: 32rpx;
  height: 83rpx;
  line-height: 83rpx;
  margin: auto;
  text-align: center;
  width: 458rpx;
}
.preservation.disabled {
  opacity: 0.6;
}
.delete {
  background: #fff;
  border: 1rpx solid #fbd128;
  border-radius: 41rpx;
  color: #181818;
  font-size: 32rpx;
  height: 83rpx;
  line-height: 83rpx;
  margin: 39rpx auto;
  text-align: center;
  width: 458rpx;
}
.remark-popup {
  padding: 40rpx 32rpx calc(40rpx + env(safe-area-inset-bottom));
}
.popup-title {
  display: block;
  font-size: 32rpx;
  font-weight: 600;
  text-align: center;
  margin-bottom: 24rpx;
  color: #181818;
}
.popup-btns {
  display: flex;
  justify-content: center;
  margin-top: 32rpx;
  gap: 29rpx;
}
.modal-btn,
.modal-cancel {
  align-items: center;
  display: flex;
  font-size: 28rpx;
  height: 69rpx;
  justify-content: center;
  width: 181rpx;
  border-radius: 35rpx;
}
.modal-btn {
  background: #f7d147;
  color: #181818;
}
.modal-cancel {
  background: #fff;
  border: 1px solid #fbd128;
  color: #7e7e7e;
}
</style>
