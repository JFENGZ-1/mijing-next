<script setup lang="ts">
import { computed, ref } from "vue";
import { onPullDownRefresh, onShow } from "@dcloudio/uni-app";
import { requireMemberAuth } from "@/auth/guard";
import { getMemberSitePublicDetail } from "@/api/member";
import { ensureMemberContext } from "@/composables/member-context";
import type { MemberSitePublicDetail } from "@/types/member";
import { formatApiErrorMessage } from "@/utils/api-error";

const errorMessage = ref("");
const detail = ref<MemberSitePublicDetail | null>(null);

const loading = ref(true);

const carouselImages = computed(() => {
  if (!detail.value) return [] as string[];
  const items = detail.value.carousel.items.map((item) => item.imageUrl);
  if (items.length > 0) return items;
  return detail.value.carousel.defaultImageUrl ? [detail.value.carousel.defaultImageUrl] : [];
});

const warmHints = computed(() => detail.value?.warmHints.filter((item) => item.hasContent) ?? []);

async function loadDetail(refresh = false) {
  errorMessage.value = "";
  if (refresh) {
    detail.value = null;
  }

  try {
    const context = await ensureMemberContext();
    if (!context) {
      errorMessage.value = "请先选择场馆";
      return;
    }

    const response = await getMemberSitePublicDetail(context.tenantId, context.siteId);
    detail.value = response.data;
  } catch (error) {
    errorMessage.value = formatApiErrorMessage(error, "场馆详情加载失败");
  } finally {
    loading.value = false;
  }
}

function callPhone() {
  if (!detail.value?.phone) return;
  uni.makePhoneCall({ phoneNumber: detail.value.phone });
}

onShow(async () => { if (await requireMemberAuth()) await loadDetail(); });

onPullDownRefresh(async () => { await loadDetail(); uni.stopPullDownRefresh(); });
</script>

<template>
  <u-loading-page :loading="loading" />
  <view v-if="!loading" class="site-detail-page">
    <u-alert v-if="errorMessage" type="error" :description="errorMessage" :custom-style="{ margin: '24rpx' }" />

    <template v-if="detail">
      <view class="hero-wrap">
        <swiper
          v-if="carouselImages.length"
          class="hero-swiper"
          circular
          indicator-dots
          indicator-color="rgba(255,255,255,0.4)"
          indicator-active-color="#ffffff"
          autoplay
          interval="4000"
        >
          <swiper-item v-for="(image, index) in carouselImages" :key="`${image}-${index}`">
            <image class="hero-image" :src="image" mode="aspectFill" lazy-load />
          </swiper-item>
        </swiper>
        <view v-else class="hero-placeholder" />
      </view>

      <view class="main-sheet card-sheet">
        <view class="shop-info">
          <view class="shop-photo">
            <image v-if="detail.logoUrl" class="logo" :src="detail.logoUrl" mode="aspectFill" lazy-load />
            <u-avatar v-else :text="detail.name.slice(0, 1)" size="55" bg-color="#22c788" />
          </view>
          <view class="shop-center">
            <text class="shop-name">{{ detail.name }}</text>
            <text v-if="detail.address" class="shop-meta">{{ detail.address }}</text>
          </view>
        </view>

        <view v-if="detail.phone" class="phone-row" @tap="callPhone">
          <text>{{ detail.phone }}</text>
          <text class="phone-action">拨打</text>
        </view>

        <view v-if="detail.description" class="section-block">
          <view class="section-title section-title--inline">场馆介绍</view>
          <view class="section-text">{{ detail.description }}</view>
        </view>

        <view v-for="hint in warmHints" :key="hint.courseType" class="section-block">
          <view class="section-title section-title--inline">{{ hint.title || hint.courseTypeLabel }}</view>
          <view class="section-text">{{ hint.text }}</view>
        </view>
      </view>
    </template>
  </view>
</template>

<style scoped lang="scss">
.site-detail-page {
  min-height: 100vh;
  background: $color-page;
}

.hero-wrap {
  height: 458rpx;
  overflow: hidden;
}

.hero-swiper,
.hero-placeholder,
.hero-image {
  width: 100%;
  height: 458rpx;
}

.hero-placeholder {
  background: linear-gradient(135deg, #22c788 0%, #1dac75 100%);
}

.main-sheet {
  position: relative;
  margin-top: -30rpx;
  min-height: 400rpx;
  padding: 44rpx 28rpx 40rpx;
}

.shop-info {
  display: flex;
  align-items: flex-start;
}

.shop-photo {
  flex-shrink: 0;
}

.logo {
  width: 110rpx;
  height: 110rpx;
  border-radius: 50%;
}

.shop-center {
  flex: 1;
  padding: 10rpx 15rpx 0;
}

.shop-name {
  display: block;
  color: $color-text;
  font-size: 42rpx;
  font-weight: 500;
}

.shop-meta {
  display: block;
  margin-top: 16rpx;
  color: $color-text-secondary;
  font-size: 22rpx;
  line-height: 26rpx;
}

.phone-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-top: 32rpx;
  padding: 24rpx;
  background: $color-surface-muted;
  border-radius: $radius-md;
  font-size: 28rpx;
}

.phone-action {
  color: $color-primary;
}

.section-block {
  margin-top: 32rpx;
}

.section-text {
  margin-top: 12rpx;
  color: $color-text-secondary;
  font-size: 26rpx;
  line-height: 1.6;
  white-space: pre-wrap;
}
</style>
