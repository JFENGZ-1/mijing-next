# Architecture

系统采用两个 uni-app 小程序加一个 PHP 模块化单体后端。

后端模块：Identity、Access、Organization、Member、Catalog、Scheduling、Booking、Entitlement、Commerce、Notification、Reporting、Audit。

第一阶段只实现 Identity、Access、Organization 的工程骨架，并为后续领域保留清晰边界。详细业务蓝图见工作区 `docs/new-system-blueprint.md`。

