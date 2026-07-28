<script setup lang="ts">
// 员工/教练列表 —— 对标原版 pagesImp/shop/staff/staff
import { computed, ref } from "vue";
import { onPullDownRefresh, onShow } from "@dcloudio/uni-app";
import { fetchStaffDirectory } from "@/api/staff-directory";
import { fetchStaffProfile } from "@/api/profile";
import { requireStaffAuth } from "@/auth/guard";
import { useSessionStore } from "@/stores/session";
import type { StaffDirectoryListItem } from "@/types/staff-directory";

const session = useSessionStore();
const loading = ref(true);
const errorMessage = ref("");
const activeItems = ref<StaffDirectoryListItem[]>([]);
const departedItems = ref<StaffDirectoryListItem[]>([]);
const activeCount = ref(0);
const departedCount = ref(0);
const myStaffId = ref(0);

const canRead = computed(() => session.can("staff.directory.read"));
const canWrite = computed(() => session.can("staff.directory.write"));

// 身份文案（原版 identName：教练 | 会籍顾问）
function identName(item: StaffDirectoryListItem) {
  const parts: string[] = [];
  if (item.capabilities.includes("coach")) parts.push("教练");
  if (item.capabilities.includes("sales")) parts.push("会籍顾问");
  return parts.join(" | ") || "员工";
}

function roleText(item: StaffDirectoryListItem) {
  return item.role?.name || "未分配";
}

async function load() {
  if (!session.currentSiteId || !canRead.value) {
    loading.value = false;
    return;
  }
  errorMessage.value = "";
  try {
    const response = await fetchStaffDirectory(session.currentSiteId);
    activeItems.value = response.items.filter((item) => item.status === "active");
    departedItems.value = response.items.filter((item) => item.status === "departed");
    activeCount.value = response.activeCount;
    departedCount.value = response.departedCount;
  } catch (error) {
    errorMessage.value = error instanceof Error ? error.message : "员工列表加载失败";
  } finally {
    loading.value = false;
  }
}

async function loadMe() {
  if (myStaffId.value) return;
  try {
    const profile = await fetchStaffProfile();
    myStaffId.value = profile.id;
  } catch {
    myStaffId.value = 0;
  }
}

function openCreate() {
  uni.navigateTo({ url: "/pages/settings/staff/edit" });
}

function openEdit(item: StaffDirectoryListItem) {
  uni.navigateTo({ url: `/pages/settings/staff/edit?id=${item.id}` });
}

function openInvite(item: StaffDirectoryListItem) {
  // 去邀请：进入编辑页内的「邀请绑定微信」分享
  uni.navigateTo({ url: `/pages/settings/staff/edit?id=${item.id}&invite=1` });
}

onShow(async () => {
  if (await requireStaffAuth()) {
    await Promise.all([load(), loadMe()]);
  }
});

onPullDownRefresh(async () => {
  await load();
  uni.stopPullDownRefresh();
});
</script>

<template>
  <u-loading-page :loading="loading" />
  <view v-if="!loading" class="page-shell">
    <view class="body-sheet">
      <u-alert v-if="errorMessage" type="error" :description="errorMessage" />
      <u-empty v-if="!canRead" mode="permission" text="暂无查看员工目录权限" />

      <template v-else>
        <!-- 顶部统计（原版 top） -->
        <view class="top-row">
          <text class="headcount">共{{ activeCount + departedCount }}名</text>
          <text class="sub-count">在职{{ activeCount }}人<text class="leave-count">离职{{ departedCount }}人</text></text>
        </view>

        <!-- 在职列表 -->
        <view class="staff-list">
          <view v-for="item in activeItems" :key="item.id" class="staff-block">
            <view class="staff-item">
              <view class="item-grid" @tap="openEdit(item)">
                <image v-if="item.avatarUrl" class="headimg" :src="item.avatarUrl" mode="aspectFill" />
                <view v-else class="headimg placeholder-avatar">{{ (item.displayName || "员")[0] }}</view>
                <view class="name-phone">
                  <view class="name-row">
                    <text class="name">{{ item.displayName }}<text v-if="myStaffId === item.id">(我)</text></text>
                    <text v-if="item.gender === 'male'" class="sex male">♂</text>
                    <text v-else-if="item.gender === 'female'" class="sex female">♀</text>
                  </view>
                  <text class="ident">{{ identName(item) }}</text>
                </view>
              </view>
              <view class="right-op">
                <view v-if="!item.hasWechatBinding" class="invite-btn" @tap.stop="openInvite(item)">去邀请</view>
                <text class="position" @tap.stop="openEdit(item)">
                  <template v-if="item.isSiteOwner">权属人ㆍ</template>{{ roleText(item) }}
                </text>
                <u-icon
                  v-if="!item.isSiteOwner || myStaffId === item.id"
                  name="arrow-right"
                  size="16"
                  color="#989898"
                  @tap.stop="openEdit(item)"
                />
              </view>
            </view>
            <view class="row-line" />
          </view>
        </view>

        <!-- 离职人员（原版 activeleaveOffice） -->
        <view v-if="departedItems.length" class="leave-section">
          <view class="leave-title">离职人员</view>
          <view v-for="item in departedItems" :key="`departed-${item.id}`" class="staff-block">
            <view class="staff-item leave" @tap="openEdit(item)">
              <view class="item-grid">
                <image v-if="item.avatarUrl" class="headimg grey" :src="item.avatarUrl" mode="aspectFill" />
                <view v-else class="headimg placeholder-avatar grey">{{ (item.displayName || "员")[0] }}</view>
                <view class="name-phone">
                  <view class="name-row">
                    <text class="name leave-name">{{ item.displayName }}</text>
                    <text v-if="item.gender === 'male'" class="sex male">♂</text>
                    <text v-else-if="item.gender === 'female'" class="sex female">♀</text>
                  </view>
                  <text class="ident dim">{{ identName(item) }}</text>
                </view>
              </view>
              <view class="right-op">
                <text class="leave-tag">已离职</text>
                <text class="position dim">{{ roleText(item) }}</text>
                <u-icon name="arrow-right" size="16" color="#989898" />
              </view>
            </view>
            <view class="row-line" />
          </view>
        </view>

        <view class="brand-footer">松果约课</view>
      </template>
    </view>

    <!-- 浮动添加按钮（原版 create-Employee 双层黄圆） -->
    <view v-if="canWrite && canRead" class="create-fab" @tap="openCreate">
      <view class="fab-bg">
        <text>添加</text>
      </view>
    </view>
  </view>
</template>

<style scoped lang="scss">
.page-shell {
  min-height: 100vh;
  background: $color-brand-yellow;
}

.body-sheet {
  min-height: 100vh;
  padding: 42rpx 26rpx 70rpx;
  background: #fff;
  border-radius: 20rpx 20rpx 0 0;
  box-sizing: border-box;
}

.top-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.headcount {
  color: #181818;
  font-size: 28rpx;
}

.sub-count {
  color: #989898;
  font-size: 22rpx;
}

.leave-count {
  padding-left: 20rpx;
}

.staff-list {
  padding: 30rpx 5rpx 60rpx 13rpx;
}

.staff-item {
  display: flex;
  justify-content: space-between;
  padding-top: 20rpx;

  &.leave {
    opacity: 0.8;
  }
}

.item-grid {
  display: flex;
  flex: 1;
  min-width: 0;
}

.headimg {
  flex-shrink: 0;
  width: 96rpx;
  height: 96rpx;
  border-radius: 10rpx;

  &.grey {
    filter: grayscale(100%);
  }
}

.placeholder-avatar {
  display: flex;
  align-items: center;
  justify-content: center;
  background: $color-page;
  color: $color-text-secondary;
  font-size: 34rpx;
}

.name-phone {
  display: flex;
  flex-direction: column;
  justify-content: center;
  min-width: 0;
  padding-left: 20rpx;
}

.name-row {
  display: flex;
  align-items: center;
}

.name {
  overflow: hidden;
  max-width: 260rpx;
  color: #181818;
  font-size: 28rpx;
  font-weight: 500;
  white-space: nowrap;
  text-overflow: ellipsis;

  &.leave-name {
    color: #989898;
    font-weight: bolder;
  }
}

.sex {
  margin-left: 8rpx;
  font-size: 24rpx;
  font-weight: 600;

  &.male {
    color: #4d9ff0;
  }

  &.female {
    color: #f06e9c;
  }
}

.ident {
  margin-top: 12rpx;
  color: #989898;
  font-size: 22rpx;

  &.dim {
    color: #dadada;
  }
}

.right-op {
  display: flex;
  flex-shrink: 0;
  align-items: center;
  justify-content: flex-end;
}

.invite-btn {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 112rpx;
  height: 44rpx;
  margin-right: 17rpx;
  border: 1rpx solid #e98932;
  border-radius: 50rpx;
  color: #e98933;
  font-size: 20rpx;
}

.position {
  padding-right: 12rpx;
  color: #989898;
  font-size: 26rpx;

  &.dim {
    color: #dadada;
  }
}

.leave-tag {
  margin-right: 20rpx;
  padding: 4rpx 15rpx;
  background: #faf5f8;
  border-radius: 24rpx;
  color: #d95872;
  font-size: 18rpx;
  line-height: 26rpx;
}

.row-line {
  height: 1rpx;
  margin: 20rpx 28rpx 0 120rpx;
  background: #f0f0f0;
}

.leave-section {
  padding: 0 5rpx 0 13rpx;
}

.leave-title {
  padding-bottom: 20rpx;
  color: #181818;
  font-size: 28rpx;
}

.brand-footer {
  margin: 90rpx 0 20rpx;
  color: #d8d8d8;
  font-size: 26rpx;
  letter-spacing: 6rpx;
  text-align: center;
}

// —— 浮动添加（原版 create-Employee：139 外圈 + 125 黄圆） ——
.create-fab {
  position: fixed;
  right: 38rpx;
  bottom: 180rpx;
  display: flex;
  align-items: center;
  justify-content: center;
  width: 139rpx;
  height: 139rpx;
  background: #fff6de;
  border-radius: 50%;
}

.fab-bg {
  display: flex;
  align-items: center;
  justify-content: center;
  flex-direction: column;
  width: 125rpx;
  height: 125rpx;
  background: #fbd128;
  border-radius: 50%;
  color: #181818;
  font-size: 32rpx;
}
</style>
