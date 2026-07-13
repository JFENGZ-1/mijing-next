(global.webpackJsonp = global.webpackJsonp || []).push([
  ["pageReport/rank/memberPointConfig"],
  {
    "4a56": function (t, e, n) {
      "use strict";
      (function (t) {
        var a = n("47a9");
        Object.defineProperty(e, "__esModule", { value: !0 }),
          (e.default = void 0);
        var i = a(n("3b2d")),
          o = n("4689"),
          s = {
            data: function () {
              return {
                start: !1,
                placeholder:
                  "如：团课签到一节1积分，小班课1节2积分，私教1节3积分，约课并签到后积分有效！积分可用于兑换瑜伽垫、代金卡（可买课抵扣）等",
                placeholder1: "",
                blogContent: "",
                editorCtx: "",
                num: 0,
                editorConter: "",
                data: {},
                maxLength: 500,
                eChecked: !1,
                status: 0,
                start1: 0,
                allShow: !0,
              };
            },
            components: {
              navigation: function () {
                n.e("components/navigation/index")
                  .then(
                    function () {
                      return resolve(n("af9e"));
                    }.bind(null, n),
                  )
                  .catch(n.oe);
              },
              confirmModal: function () {
                n.e("components/confirm-modal/confirm-modal")
                  .then(
                    function () {
                      return resolve(n("4e5b"));
                    }.bind(null, n),
                  )
                  .catch(n.oe);
              },
            },
            computed: {
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
              logonUserInfo: function () {
                return this.$store.state.logonUserInfo;
              },
            },
            methods: {
              checkboxChange: function (t) {
                "team" == t.name
                  ? (this.data.teamCheck = t.value)
                  : "private" == t.name
                    ? (this.data.privateCheck = t.value)
                    : "buyCard" == t.name && (this.data.buyCardCheck = t.value),
                  this.$forceUpdate();
              },
              headlestart: function (t) {
                t
                  ? ((this.data.teamSignVal = 10),
                    (this.data.privateSignVal = 10),
                    (this.data.teamCheck = !0),
                    (this.data.privateCheck = !0),
                    this.$forceUpdate(),
                    (this.start1 = 1))
                  : ((this.status = 1), (this.$refs.confirmModal.show = !0));
              },
              onEditorReady: function () {
                var e = this;
                (e.editorConter = e.data.descText),
                  e
                    .createSelectorQuery()
                    .select("#editor")
                    .context(function (n) {
                      (e.editorCtx = n.context),
                        e.data &&
                          e.data.descText &&
                          (e.editorCtx.setContents({ html: e.editorConter }),
                          n.context.blur(),
                          t.pageScrollTo({ scrollTop: 0, duration: 0 }),
                          (e.num =
                            e.data.descText.replace(/<[^>]+>/g, "").length -
                            1));
                    })
                    .exec();
              },
              cancelbtn1: function () {
                1 == this.status && (this.start = 1),
                  (this.eChecked = !1),
                  (this.$refs.confirmModal.show = !1);
              },
              cancelbtn: function () {
                (this.eChecked = !1), (this.$refs.confirmModal.show = !1);
              },
              pointConfirm: function () {
                3 == this.status || this.eChecked
                  ? (this.cancelbtn(),
                    1 == this.status
                      ? this.saveUserPointConfig()
                      : 2 == this.status
                        ? ((this.status = 3),
                          (this.eChecked = !0),
                          (this.$refs.confirmModal.show = !0))
                        : 3 == this.status &&
                          ((this.$refs.confirmModal.show = !1),
                          (this.eChecked = !1),
                          (0, o.clearUserPoint)({}).then(function (e) {
                            200 == e.code
                              ? t.showToast({
                                  title: "积分已清除",
                                  icon: "none",
                                  mask: !0,
                                  success: function () {
                                    setTimeout(function () {
                                      t.navigateBack();
                                    }, 1e3);
                                  },
                                })
                              : t.showToast({
                                  title: e.msg,
                                  icon: "none",
                                  mask: !0,
                                });
                          })))
                  : t.showToast({
                      icon: "none",
                      title: "请先点击「我已清楚」",
                    });
              },
              confirm: function () {
                this.$refs.confirmPermissionModal.show = !1;
              },
              editFocus: function () {
                this.start &&
                  0 == this.num &&
                  (this.editorCtx.setContents({ html: this.placeholder1 }),
                  (this.num =
                    this.placeholder1.replace(/<[^>]+>/g, "").length - 1));
              },
              onInput: function (t) {
                this.num = t.detail.text.replace(/<[^>]+>/g, "").length - 1;
              },
              getInit: function () {
                var t = this;
                (0, o.getUserPointConfig)({}).then(function (e) {
                  var n = JSON.parse(
                    JSON.stringify(e.data)
                      .replace(/:null/g, ':""')
                      .replace(/: null/g, ': ""'),
                  );
                  (t.data = n),
                    1 == n.start
                      ? ((t.start = !0), (t.start1 = 1))
                      : (t.start = !1),
                    t.data.teamSignVal && t.data.teamSignVal > 0
                      ? (t.data.teamCheck = !0)
                      : (t.data.teamCheck = !1),
                    t.data.privateSignVal && t.data.privateSignVal > 0
                      ? (t.data.privateCheck = !0)
                      : (t.data.privateCheck = !1),
                    t.data.buyCardVal && t.data.buyCardVal > 0
                      ? (t.data.buyCardCheck = !0)
                      : (t.data.buyCardCheck = !1);
                });
              },
              submit: function () {
                var e = this,
                  n = this;
                this.editorCtx.getContents({
                  success: function (a) {
                    if (n.num > n.maxLength)
                      t.showToast({
                        icon: "none",
                        title: "内容不能超过" + n.maxLength + "字",
                      });
                    else {
                      if (e.start) {
                        var i = /^[1-9]\d*$/;
                        if (e.data.teamCheck) {
                          if (!i.test(e.data.teamSignVal))
                            return void t.showToast({
                              title: "请输入团课积分",
                              duration: 2e3,
                              icon: "none",
                            });
                          if (e.data.teamSignVal >= 1e4)
                            return void t.showToast({
                              title: "团课每次积分须在10000内",
                              duration: 2e3,
                              icon: "none",
                            });
                        }
                        if (e.data.privateCheck) {
                          if (!i.test(e.data.privateSignVal))
                            return void t.showToast({
                              title: "请输入私教积分",
                              duration: 2e3,
                              icon: "none",
                            });
                          if (e.data.privateSignVal >= 1e4)
                            return void t.showToast({
                              title: "私教每次积分须在10000内",
                              duration: 2e3,
                              icon: "none",
                            });
                        }
                        if (e.data.buyCardCheck) {
                          if (!i.test(e.data.buyCardVal))
                            return void t.showToast({
                              title: "请输入购卡/发卡积分",
                              duration: 2e3,
                              icon: "none",
                            });
                          if (e.data.buyCardVal >= 1e4)
                            return void t.showToast({
                              title: "购卡/发卡每元积分须在10000内",
                              duration: 2e3,
                              icon: "none",
                            });
                        }
                      }
                      "<p><br></p>" == a.html
                        ? (n.editorConter = "")
                        : (n.editorConter = a.html),
                        (n.data.descText = n.editorConter),
                        1 == n.data.start
                          ? n.start
                            ? e.saveUserPointConfig()
                            : ((e.$refs.confirmModal.show = !0), (e.status = 1))
                          : e.saveUserPointConfig();
                    }
                  },
                });
              },
              saveUserPointConfig: function () {
                this.start
                  ? ((this.data.start = 1), (this.start1 = 1))
                  : ((this.data.start = 0), (this.start1 = 0));
                var e = this.removeObjectsWithNullInSecondLevel(this.data);
                (0, o.saveUserPointConfig)(e).then(function (e) {
                  200 == e.code
                    ? t.showToast({
                        title: "修改成功",
                        icon: "none",
                        mask: !0,
                        success: function () {
                          setTimeout(function () {
                            var e = getCurrentPages(),
                              n = e[e.length - 2];
                            n && n.$vm.reGetList && n.$vm.reGetInit(),
                              setTimeout(function () {
                                t.navigateBack();
                              }, 500);
                          }, 500);
                        },
                      })
                    : t.showToast({ title: e.msg, icon: "none", mask: !0 });
                });
              },
              onClear: function () {
                2 == this.logonUserInfo.staffType ||
                3 == this.logonUserInfo.staffType
                  ? ((this.status = 2), (this.$refs.confirmModal.show = !0))
                  : (this.$refs.confirmPermissionModal.show = !0);
              },
              removeObjectsWithNullInSecondLevel: function (t) {
                var e = {};
                return (
                  this.start
                    ? ((e.start = 1),
                      (e.pointCondition = t.pointCondition),
                      (e.descText = t.descText),
                      this.data.teamCheck &&
                        ((e.teamSignVal = t.teamSignVal),
                        (e.tagList = []),
                        (e.tagList = t.tagList.filter(function (t) {
                          return (
                            "object" != (0, i.default)(t) ||
                            null == t ||
                            !Object.values(t).some(function (t) {
                              return null == t || "" == t;
                            })
                          );
                        }))),
                      this.data.privateCheck &&
                        (e.privateSignVal = t.privateSignVal),
                      this.data.buyCardCheck && (e.buyCardVal = t.buyCardVal))
                    : ((e.start = 0), (e.pointCondition = t.pointCondition)),
                  e
                );
              },
              headleCreal: function () {
                (this.blogContent = ""),
                  (this.num = 0),
                  (this.editorConter = ""),
                  this.editorCtx.clear();
              },
            },
            onLoad: function () {
              this.getInit();
            },
          };
        e.default = s;
      }).call(this, n("df3c").default);
    },
    "960d": function (t, e, n) {
      "use strict";
      n.d(e, "b", function () {
        return i;
      }),
        n.d(e, "c", function () {
          return o;
        }),
        n.d(e, "a", function () {
          return a;
        });
      var a = {
          uSwitch: function () {
            return n
              .e("uview-ui/components/u-switch/u-switch")
              .then(n.bind(null, "a048"));
          },
          uRadioGroup: function () {
            return Promise.all([
              n.e("common/vendor"),
              n.e("uview-ui/components/u-radio-group/u-radio-group"),
            ]).then(n.bind(null, "aed4"));
          },
          uRadio: function () {
            return n
              .e("uview-ui/components/u-radio/u-radio")
              .then(n.bind(null, "acf8"));
          },
          uCheckbox: function () {
            return n
              .e("uview-ui/components/u-checkbox/u-checkbox")
              .then(n.bind(null, "199f"));
          },
          ffBottomLogo: function () {
            return n
              .e("components/ff-bottom-logo/ff-bottom-logo")
              .then(n.bind(null, "3111"));
          },
          confirmModal: function () {
            return n
              .e("components/confirm-modal/confirm-modal")
              .then(n.bind(null, "4e5b"));
          },
        },
        i = function () {
          var t = this,
            e =
              (t.$createElement,
              t._self._c,
              1 == t.start1
                ? t.__map(t.data.tagList, function (e, n) {
                    return {
                      $orig: t.__get_orig(e),
                      m0: t.data.teamCheck ? t.$shorten(e.tagData, 8) : null,
                    };
                  })
                : null),
            n =
              1 == t.start1
                ? t.data.teamCheck &&
                  t.data.tagList &&
                  t.data.tagList.length > 0
                : null,
            a = 1 != t.start1 ? t.imgsrc("/static/imgs/nodata.png") : null;
          t.$mp.data = Object.assign({}, { $root: { l0: e, g0: n, m1: a } });
        },
        o = [];
    },
    "9bc5": function (t, e, n) {
      "use strict";
      n.r(e);
      var a = n("960d"),
        i = n("a110");
      for (var o in i)
        ["default"].indexOf(o) < 0 &&
          (function (t) {
            n.d(e, t, function () {
              return i[t];
            });
          })(o);
      n("b433");
      var s = n("828b"),
        r = Object(s.a)(
          i.default,
          a.b,
          a.c,
          !1,
          null,
          "28a196ff",
          null,
          !1,
          a.a,
          void 0,
        );
      e.default = r.exports;
    },
    a110: function (t, e, n) {
      "use strict";
      n.r(e);
      var a = n("4a56"),
        i = n.n(a);
      for (var o in a)
        ["default"].indexOf(o) < 0 &&
          (function (t) {
            n.d(e, t, function () {
              return a[t];
            });
          })(o);
      e.default = i.a;
    },
    b433: function (t, e, n) {
      "use strict";
      var a = n("e5ce");
      n.n(a).a;
    },
    d479: function (t, e, n) {
      "use strict";
      (function (t, e) {
        var a = n("47a9");
        n("86d2"), a(n("3240"));
        var i = a(n("9bc5"));
        (t.__webpack_require_UNI_MP_PLUGIN__ = n), e(i.default);
      }).call(this, n("3223").default, n("df3c").createPage);
    },
    e5ce: function (t, e, n) {},
  },
  [["d479", "common/runtime", "common/vendor"]],
]);
