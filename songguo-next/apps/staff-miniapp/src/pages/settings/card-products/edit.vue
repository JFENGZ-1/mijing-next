<script setup lang="ts">
import { computed, ref } from "vue";
import { onLoad, onShow } from "@dcloudio/uni-app";
import { fetchStaffCourseCatalog } from "@/api/catalog";
import {
  archiveCardProduct,
  createCardProduct,
  fetchCardProduct,
  updateCardProduct,
} from "@/api/card-products";
import { requireStaffAuth } from "@/auth/guard";
import { useSessionStore } from "@/stores/session";
import type { CourseCatalogItem } from "@/types/catalog";
import type {
  StaffCardProductCourseScopeInput,
  StaffCardProductDetail,
  StaffCardProductUpsertPayload,
} from "@/types/member-cards";

const CARD_TYPES: StaffCardProductUpsertPayload["cardType"][] = ["stored_value", "count", "period"];

interface CourseScopeDraft {
  courseId: number;
  name: string;
  selected: boolean;
  priceOverride: string;
}

const session = useSessionStore();
const loading = ref(true);
const saving = ref(false);
const errorMessage = ref("");
const productId = ref<number | null>(null);
const product = ref<StaffCardProductDetail | null>(null);
const courses = ref<CourseCatalogItem[]>([]);
const courseScopes = ref<CourseScopeDraft[]>([]);
const cardType = ref<StaffCardProductUpsertPayload["cardType"]>("count");
const name = ref("");
const description = ref("");
const price = ref("");
const faceValue = ref("");
const initialCount = ref("");
const validityDays = ref("");
const saleOn = ref(true);
const sortOrder = ref("0");

const isEdit = computed(() => productId.value !== null);
const canWrite = computed(() => session.can("card-product.editor.write"));
const canArchive = computed(() => session.can("card-product.archive"));
const isStoredValue = computed(() => cardType.value === "stored_value");
const isCount = computed(() => cardType.value === "count");
const isPeriod = computed(() => cardType.value === "period");
const showPriceMatrix = computed(() => isCount.value || isStoredValue.value);
const selectedCourseCount = computed(() => courseScopes.value.filter((scope) => scope.selected).length);
const cardTypeLabel = computed(
  () => ({ stored_value: "储值卡", count: "次卡", period: "期限卡" })[cardType.value],
);

function cardTypeLabels() {
  return CARD_TYPES.map((type) => ({ stored_value: "储值卡", count: "次卡", period: "期限卡" })[type]);
}

function priceOverrideLabel() {
  if (isCount.value) return "扣次单价（可选）";
  if (isStoredValue.value) return "单次扣费（可选）";
  return "价格覆盖";
}

function priceOverridePlaceholder() {
  if (isCount.value) return "该课程每次扣几次";
  if (isStoredValue.value) return "该课程单次扣费金额";
  return "覆盖价格";
}

function initCourseScopes(catalog: CourseCatalogItem[], detail?: StaffCardProductDetail) {
  const existingByCourseId = new Map(
    (detail?.courseScopes ?? [])
      .filter((scope) => scope.scopeKind === "single")
      .map((scope) => [Number(scope.scopeKey), scope]),
  );

  courseScopes.value = catalog.map((course) => {
    const existing = existingByCourseId.get(course.id);
    return {
      courseId: course.id,
      name: course.name,
      selected: existing != null,
      priceOverride: existing?.priceOverride ?? "",
    };
  });
}

function fillForm(detail: StaffCardProductDetail) {
  cardType.value = detail.cardType as StaffCardProductUpsertPayload["cardType"];
  name.value = detail.name;
  description.value = detail.description || "";
  price.value = detail.price;
  faceValue.value = detail.faceValue || "";
  initialCount.value = detail.initialCount != null ? String(detail.initialCount) : "";
  validityDays.value = detail.validityDays != null ? String(detail.validityDays) : "";
  saleOn.value = detail.saleStatus === "on_sale";
  sortOrder.value = String(detail.sortOrder);
}

function toggleCourseScope(courseId: number, selected: boolean) {
  courseScopes.value = courseScopes.value.map((scope) =>
    scope.courseId === courseId ? { ...scope, selected } : scope,
  );
}

function isCourseSelected(courseId: number) {
  return courseScopes.value.find((scope) => scope.courseId === courseId)?.selected ?? false;
}

function coursePriceOverride(courseId: number) {
  return courseScopes.value.find((scope) => scope.courseId === courseId)?.priceOverride ?? "";
}

function updateCoursePriceOverride(courseId: number, value: string) {
  courseScopes.value = courseScopes.value.map((scope) =>
    scope.courseId === courseId ? { ...scope, priceOverride: value } : scope,
  );
}

function parsePrice(value: string, label: string): number | null {
  const trimmed = value.trim();
  if (!trimmed) return null;
  const amount = Number(trimmed);
  if (!Number.isFinite(amount) || amount < 0) {
    uni.showToast({ title: `请输入有效${label}`, icon: "none" });
    return null;
  }
  return amount;
}

function parsePositiveInt(value: string, label: string): number | null {
  const trimmed = value.trim();
  if (!trimmed) return null;
  const amount = Number.parseInt(trimmed, 10);
  if (!Number.isFinite(amount) || amount < 1) {
    uni.showToast({ title: `请输入有效${label}`, icon: "none" });
    return null;
  }
  return amount;
}

function parseOptionalPrice(value: string): number | null {
  const trimmed = value.trim();
  if (!trimmed) return null;
  const amount = Number(trimmed);
  if (!Number.isFinite(amount) || amount < 0) return null;
  return amount;
}

function buildCourseScopePayload(): StaffCardProductCourseScopeInput[] | null {
  const scopes: StaffCardProductCourseScopeInput[] = [];
  for (const [index, scope] of courseScopes.value.filter((item) => item.selected).entries()) {
    const override = parseOptionalPrice(scope.priceOverride);
    if (scope.priceOverride.trim() && override == null) {
      uni.showToast({ title: `请输入有效${priceOverrideLabel()}`, icon: "none" });
      return null;
    }
    const payload: StaffCardProductCourseScopeInput = {
      scopeKind: "single",
      scopeKey: String(scope.courseId),
      displayName: scope.name,
      sortOrder: index,
    };
    if (override != null) payload.priceOverride = override;
    scopes.push(payload);
  }
  return scopes;
}

function buildPayload(): StaffCardProductUpsertPayload | null {
  if (!name.value.trim()) {
    uni.showToast({ title: "请填写卡种名称", icon: "none" });
    return null;
  }
  const parsedPrice = parsePrice(price.value, "售价");
  if (parsedPrice == null) return null;
  const courseScopePayload = buildCourseScopePayload();
  if (courseScopePayload == null) return null;

  const payload: StaffCardProductUpsertPayload = {
    cardType: cardType.value,
    name: name.value.trim(),
    price: parsedPrice,
    saleStatus: saleOn.value ? "on_sale" : "stopped",
    sortOrder: Number.parseInt(sortOrder.value, 10) || 0,
    courseScopes: courseScopePayload,
  };
  if (description.value.trim()) payload.description = description.value.trim();

  if (isStoredValue.value) {
    const parsedFaceValue = parsePrice(faceValue.value, "面值");
    if (parsedFaceValue == null) return null;
    payload.faceValue = parsedFaceValue;
  }
  if (isCount.value) {
    const parsedCount = parsePositiveInt(initialCount.value, "初始次数");
    if (parsedCount == null) return null;
    payload.initialCount = parsedCount;
  }
  if (isPeriod.value) {
    const parsedDays = parsePositiveInt(validityDays.value, "有效天数");
    if (parsedDays == null) return null;
    payload.validityDays = parsedDays;
  }
  if (isCount.value && validityDays.value.trim()) {
    const parsedDays = parsePositiveInt(validityDays.value, "有效天数");
    if (parsedDays == null) return null;
    payload.validityDays = parsedDays;
  }

  return payload;
}

async function loadCourses() {
  if (!session.currentSiteId || !session.can("course-catalog.read")) {
    courses.value = [];
    initCourseScopes([]);
    return;
  }
  const catalog = await fetchStaffCourseCatalog(session.currentSiteId, 1, 100);
  courses.value = catalog.items;
  initCourseScopes(catalog.items, product.value ?? undefined);
}

async function load() {
  if (!canWrite.value) {
    loading.value = false;
    return;
  }
  loading.value = true;
  errorMessage.value = "";
  try {
    if (isEdit.value && session.currentSiteId && productId.value) {
      const response = await fetchCardProduct(session.currentSiteId, productId.value);
      product.value = response.data;
      fillForm(response.data);
    }
    await loadCourses();
    if (product.value) {
      initCourseScopes(courses.value, product.value);
    }
  } catch (error) {
    errorMessage.value = error instanceof Error ? error.message : "卡种资料加载失败";
  } finally {
    loading.value = false;
  }
}

function chooseCardType() {
  if (isEdit.value) return;
  uni.showActionSheet({
    itemList: cardTypeLabels(),
    success: ({ tapIndex }) => {
      cardType.value = CARD_TYPES[tapIndex];
      faceValue.value = "";
      initialCount.value = "";
      validityDays.value = "";
    },
  });
}

async function save() {
  if (!session.currentSiteId) return;
  const payload = buildPayload();
  if (!payload) return;

  saving.value = true;
  errorMessage.value = "";
  try {
    if (isEdit.value && product.value) {
      const response = await updateCardProduct(session.currentSiteId, product.value.id, {
        ...payload,
        version: product.value.version,
      });
      product.value = response.data;
      fillForm(response.data);
      initCourseScopes(courses.value, response.data);
    } else {
      const response = await createCardProduct(session.currentSiteId, payload);
      product.value = response.data;
      productId.value = response.data.id;
      fillForm(response.data);
      initCourseScopes(courses.value, response.data);
    }
    uni.showToast({ title: "保存成功", icon: "none" });
    setTimeout(() => uni.navigateBack(), 600);
  } catch (error) {
    errorMessage.value = error instanceof Error ? error.message : "保存失败";
  } finally {
    saving.value = false;
  }
}

async function archive() {
  if (!session.currentSiteId || !product.value || !canArchive.value) return;
  const confirmed = await new Promise<boolean>((resolve) => {
    uni.showModal({
      title: "确认归档",
      content: `归档后「${product.value?.name ?? ""}」将不再出现在卡种列表，已发出的会员卡不受影响。`,
      success: (result) => resolve(Boolean(result.confirm)),
    });
  });
  if (!confirmed) return;

  saving.value = true;
  try {
    await archiveCardProduct(session.currentSiteId, product.value.id);
    uni.showToast({ title: "已归档", icon: "none" });
    setTimeout(() => uni.navigateBack(), 600);
  } catch (error) {
    errorMessage.value = error instanceof Error ? error.message : "归档失败";
  } finally {
    saving.value = false;
  }
}

onLoad((query) => {
  if (query?.id) productId.value = Number(query.id);
});

onShow(async () => {
  if (await requireStaffAuth()) await load();
});
</script>

<template>
  <u-loading-page :loading="loading || saving" />
  <view v-if="!loading" class="page-container">
    <u-alert v-if="errorMessage" type="error" :description="errorMessage" />
    <u-empty v-if="!canWrite" mode="permission" text="暂无编辑卡种权限" />

    <template v-else>
      <view class="field-block">
        <view class="field-label">卡种类型</view>
        <view class="picker-row" :class="{ disabled: isEdit }" @click="chooseCardType">
          <text class="picker-value">{{ cardTypeLabel }}</text>
          <u-icon v-if="!isEdit" name="arrow-right" size="16" color="#999" />
        </view>
      </view>

      <view class="field-block">
        <view class="field-label">卡种名称</view>
        <u-input v-model="name" placeholder="如：瑜伽 10 次卡" maxlength="120" />
      </view>

      <view class="field-block">
        <view class="field-label">售价（元）</view>
        <u-input v-model="price" type="digit" placeholder="售卖价格" />
      </view>

      <view v-if="isStoredValue" class="field-block">
        <view class="field-label">面值（元）</view>
        <u-input v-model="faceValue" type="digit" placeholder="储值卡到账金额" />
      </view>

      <view v-if="isCount" class="field-block">
        <view class="field-label">初始次数</view>
        <u-input v-model="initialCount" type="number" placeholder="开卡默认次数" />
      </view>

      <view v-if="isPeriod || isCount" class="field-block">
        <view class="field-label">{{ isPeriod ? "有效天数" : "有效天数（可选）" }}</view>
        <u-input v-model="validityDays" type="number" placeholder="自激活起有效天数" />
      </view>

      <view class="field-block">
        <view class="field-label">适用课程（{{ selectedCourseCount }} / {{ courseScopes.length }}）</view>
        <text v-if="courseScopes.length === 0" class="field-hint">暂无课程目录，请先在课程管理中创建课程</text>
        <view v-for="scope in courseScopes" :key="scope.courseId" class="course-scope-item">
          <u-checkbox
            :checked="isCourseSelected(scope.courseId)"
            :label="scope.name"
            @change="(checked: boolean) => toggleCourseScope(scope.courseId, checked)"
          />
          <view v-if="showPriceMatrix && isCourseSelected(scope.courseId)" class="scope-price-row">
            <text class="scope-price-label">{{ priceOverrideLabel() }}</text>
            <u-input
              :model-value="coursePriceOverride(scope.courseId)"
              type="digit"
              :placeholder="priceOverridePlaceholder()"
              @update:model-value="(value: string) => updateCoursePriceOverride(scope.courseId, value)"
            />
          </view>
        </view>
      </view>

      <view class="field-block">
        <view class="field-label">备注说明（可选）</view>
        <u-input v-model="description" placeholder="卡种说明" maxlength="500" />
      </view>

      <view class="field-block switch-row">
        <text class="field-label inline">在售状态</text>
        <u-switch v-model="saleOn" />
        <text class="switch-hint">{{ saleOn ? "在售" : "停售" }}</text>
      </view>

      <view class="field-block">
        <view class="field-label">排序（可选）</view>
        <u-input v-model="sortOrder" type="number" placeholder="数字越小越靠前" />
      </view>

      <view class="actions">
        <u-button type="primary" :loading="saving" @click="save">{{ isEdit ? "保存修改" : "创建卡种" }}</u-button>
        <u-button
          v-if="isEdit && canArchive"
          type="error"
          plain
          :loading="saving"
          text="归档卡种"
          @click="archive"
        />
      </view>
    </template>
  </view>
</template>

<style scoped lang="scss">
.page-container {
  padding: 24rpx;
  padding-bottom: 48rpx;
}

.field-block {
  margin-bottom: 28rpx;
}

.field-label {
  margin-bottom: 12rpx;
  font-size: 28rpx;
  font-weight: 600;
  color: #202124;
}

.field-label.inline {
  margin-bottom: 0;
}

.field-hint {
  display: block;
  margin-bottom: 12rpx;
  color: #5f6368;
  font-size: 24rpx;
}

.picker-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 24rpx;
  background: #f4f6f8;
  border: 1rpx solid #e0e3e7;
  border-radius: 12rpx;
}

.picker-row.disabled {
  opacity: 0.7;
}

.picker-value {
  font-size: 28rpx;
}

.course-scope-item {
  padding: 16rpx 0;
  border-bottom: 1rpx solid #f0f0f0;
}

.scope-price-row {
  display: flex;
  align-items: center;
  gap: 16rpx;
  margin-top: 12rpx;
  padding-left: 48rpx;
}

.scope-price-label {
  flex-shrink: 0;
  color: #5f6368;
  font-size: 24rpx;
}

.switch-row {
  display: flex;
  align-items: center;
  gap: 16rpx;
}

.switch-hint {
  color: #5f6368;
  font-size: 26rpx;
}

.actions {
  display: flex;
  flex-direction: column;
  gap: 20rpx;
  margin-top: 32rpx;
}
</style>
