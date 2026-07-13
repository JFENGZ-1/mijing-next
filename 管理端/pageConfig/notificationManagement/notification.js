require("../common/vendor.js"),
  (global.webpackJsonp = global.webpackJsonp || []).push([
    ["pageConfig/notificationManagement/notification"],
    {
      "3e3e": function (t, e, n) {
        "use strict";
        n.d(e, "b", function () {
          return i;
        }),
          n.d(e, "c", function () {
            return s;
          }),
          n.d(e, "a", function () {
            return o;
          });
        var o = {
            uInput: function () {
              return Promise.all([
                n.e("common/vendor"),
                n.e("uview-ui/components/u-input/u-input"),
              ]).then(n.bind(null, "b5ea"));
            },
            ffBottomLogo: function () {
              return n
                .e("components/ff-bottom-logo/ff-bottom-logo")
                .then(n.bind(null, "3111"));
            },
          },
          i = function () {
            this.$createElement;
            var t = (this._self._c, this.commonList.noticeTitle.length),
              e = this.commonList.noticeText.length;
            this.$mp.data = Object.assign({}, { $root: { g0: t, g1: e } });
          },
          s = [];
      },
      "7c11": function (t, e, n) {
        "use strict";
        (function (t) {
          var o = n("47a9");
          Object.defineProperty(e, "__esModule", { value: !0 }),
            (e.default = void 0);
          var i = o(n("7ca3")),
            s = n("8f59"),
            c = n("baeb");
          function a(t, e) {
            var n = Object.keys(t);
            if (Object.getOwnPropertySymbols) {
              var o = Object.getOwnPropertySymbols(t);
              e &&
                (o = o.filter(function (e) {
                  return Object.getOwnPropertyDescriptor(t, e).enumerable;
                })),
                n.push.apply(n, o);
            }
            return n;
          }
          var r = {
            components: {
              confirm: function () {
                n.e("pageConfig/components/confirm-modal/index")
                  .then(
                    function () {
                      return resolve(n("243c"));
                    }.bind(null, n),
                  )
                  .catch(n.oe);
              },
            },
            data: function () {
              return {
                value: "",
                type: "text",
                titleNum: 0,
                textareaNum: 0,
                editModal: !1,
                deleteModal: !1,
                deleteEndModal: !1,
                commonList: {
                  noticeId: "",
                  noticeTitle: "",
                  showDays: "",
                  noticeText: "",
                },
                status: "",
                storeList: [],
                noticeId: "",
                focus: !1,
                parameter: { pageno: 1, pagesize: 5 },
              };
            },
            watch: {
              "commonList.noticeTitle": function (t) {
                var e = this;
                t.length >= 18 &&
                  this.$nextTick(function () {
                    e.commonList.noticeTitle = t.slice(0, 18);
                  });
              },
            },
            computed: (function (t) {
              for (var e = 1; e < arguments.length; e++) {
                var n = null != arguments[e] ? arguments[e] : {};
                e % 2
                  ? a(Object(n), !0).forEach(function (e) {
                      (0, i.default)(t, e, n[e]);
                    })
                  : Object.getOwnPropertyDescriptors
                    ? Object.defineProperties(
                        t,
                        Object.getOwnPropertyDescriptors(n),
                      )
                    : a(Object(n)).forEach(function (e) {
                        Object.defineProperty(
                          t,
                          e,
                          Object.getOwnPropertyDescriptor(n, e),
                        );
                      });
              }
              return t;
            })(
              {
                StatusBar: function () {
                  return this.$store.state.systemInfo.statusBarHeight;
                },
                CustomBar: function () {
                  var e = t.getMenuButtonBoundingClientRect();
                  return (
                    e.height +
                    2 * (e.top - (0 !== this.StatusBar ? this.StatusBar : 20)) +
                    2
                  );
                },
              },
              (0, s.mapState)(["editList"]),
            ),
            methods: {
              getList: function () {
                (0, c.getNoticeList)(this.parameter);
              },
              Click: function () {
                return "" == this.commonList.noticeTitle
                  ? (t.showToast({ title: "请输入标题", icon: "none" }), !1)
                  : "" == this.commonList.noticeText
                    ? (t.showToast({ title: "请输入通知内容", icon: "none" }),
                      !1)
                    : "" == this.commonList.showDays
                      ? (t.showToast({ title: "请输入展示天数", icon: "none" }),
                        !1)
                      : void ("" != this.commonList.noticeId
                          ? (this.$refs.editModal.show = !0)
                          : (this.$refs.confirmModal.show = !0));
              },
              headleClean: function () {
                this.commonList.noticeText = "";
              },
              DeleteModal: function () {
                1 == this.status
                  ? (this.$refs.deleteModal.show = !0)
                  : (this.$refs.deleteEndModal.show = !0);
              },
              handleDeleteCancelbtn: function () {
                1 == this.status
                  ? (this.$refs.deleteModal.show = !1)
                  : (this.$refs.deleteEndModal.show = !1);
              },
              handleDeleteDeterminebtn: function () {
                1 == this.status
                  ? ((this.$refs.deleteModal.show = !1), this.headleDelete())
                  : ((this.$refs.deleteEndModal.show = !1),
                    this.headleDelete());
              },
              headleDelete: function () {
                var e = this,
                  n = this.noticeId;
                (0, c.deletes)({ noticeId: n }).then(function (n) {
                  200 == n.code
                    ? (e.getList(),
                      t.showToast({ icon: "none", title: "删除成功" }),
                      setTimeout(function () {
                        t.navigateBack({ delta: 1 });
                      }, 1e3))
                    : t.showToast({ icon: "none", title: n.msg });
                });
              },
              handleCancelbtn: function () {
                "" != this.commonList.noticeId
                  ? (this.$refs.editModal.show = !1)
                  : (this.$refs.confirmModal.show = !1);
              },
              handleDeterminebtn: function () {
                "" != this.commonList.noticeId
                  ? ((this.$refs.editModal.show = !1), this.getNotification())
                  : ((this.$refs.confirmModal.show = !1),
                    this.getNotification());
              },
              getNotification: function () {
                var e = this,
                  n = "" != this.commonList.noticeId ? "编辑成功" : "添加成功";
                (0, c.save)(this.commonList).then(function (o) {
                  200 == o.code
                    ? (e.getList(),
                      t.showToast({ icon: "none", title: n }),
                      setTimeout(function () {
                        t.navigateBack({ delta: 1 });
                      }, 1e3))
                    : t.showToast({ icon: "none", title: o.msg });
                });
              },
            },
            onLoad: function () {
              var e = this;
              (this.storeList = this.editList),
                this.storeList.map(function (t) {
                  (e.status = t.noticeStatus),
                    (e.noticeId = t.noticeId),
                    (e.commonList.noticeId = t.noticeId),
                    (e.commonList.noticeTitle = t.noticeTitle),
                    (e.commonList.showDays = t.showDays),
                    (e.commonList.noticeText = t.noticeText);
                }),
                this.commonList.noticeId
                  ? t.setNavigationBarTitle({ title: "编辑通知" })
                  : t.setNavigationBarTitle({ title: "创建通知" });
            },
          };
          e.default = r;
        }).call(this, n("df3c").default);
      },
      "858e": function (t, e, n) {
        "use strict";
        (function (t, e) {
          var o = n("47a9");
          n("86d2"), o(n("3240"));
          var i = o(n("be3a"));
          (t.__webpack_require_UNI_MP_PLUGIN__ = n), e(i.default);
        }).call(this, n("3223").default, n("df3c").createPage);
      },
      "9c25": function (t, e, n) {
        "use strict";
        n.r(e);
        var o = n("7c11"),
          i = n.n(o);
        for (var s in o)
          ["default"].indexOf(s) < 0 &&
            (function (t) {
              n.d(e, t, function () {
                return o[t];
              });
            })(s);
        e.default = i.a;
      },
      be3a: function (t, e, n) {
        "use strict";
        n.r(e);
        var o = n("3e3e"),
          i = n("9c25");
        for (var s in i)
          ["default"].indexOf(s) < 0 &&
            (function (t) {
              n.d(e, t, function () {
                return i[t];
              });
            })(s);
        n("c558");
        var c = n("828b"),
          a = Object(c.a)(
            i.default,
            o.b,
            o.c,
            !1,
            null,
            "4dc727c5",
            null,
            !1,
            o.a,
            void 0,
          );
        e.default = a.exports;
      },
      c558: function (t, e, n) {
        "use strict";
        var o = n("d5bd");
        n.n(o).a;
      },
      d5bd: function (t, e, n) {},
    },
    [["858e", "common/runtime", "common/vendor", "pageConfig/common/vendor"]],
  ]);
