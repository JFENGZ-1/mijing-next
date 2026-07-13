(global.webpackJsonp = global.webpackJsonp || []).push([
  ["pageReport/teacherMembership/personalSalaryType"],
  {
    "47ab": function (t, n, e) {
      "use strict";
      e.d(n, "b", function () {
        return i;
      }),
        e.d(n, "c", function () {
          return a;
        }),
        e.d(n, "a", function () {
          return o;
        });
      var o = {
          uSwitch: function () {
            return e
              .e("uview-ui/components/u-switch/u-switch")
              .then(e.bind(null, "a048"));
          },
          uRadioGroup: function () {
            return Promise.all([
              e.e("common/vendor"),
              e.e("uview-ui/components/u-radio-group/u-radio-group"),
            ]).then(e.bind(null, "aed4"));
          },
          uRadio: function () {
            return e
              .e("uview-ui/components/u-radio/u-radio")
              .then(e.bind(null, "acf8"));
          },
          ffBottomLogo: function () {
            return e
              .e("components/ff-bottom-logo/ff-bottom-logo")
              .then(e.bind(null, "3111"));
          },
          confirmModal: function () {
            return e
              .e("components/confirm-modal/confirm-modal")
              .then(e.bind(null, "4e5b"));
          },
          uCheckbox: function () {
            return e
              .e("uview-ui/components/u-checkbox/u-checkbox")
              .then(e.bind(null, "199f"));
          },
        },
        i = function () {
          var t = this;
          t.$createElement;
          t._self._c,
            t._isMounted ||
              ((t.e0 = function (n) {
                t.mode = 1;
              }),
              (t.e1 = function (n) {
                t.mode = 2;
              }),
              (t.e2 = function (n) {
                t.mode = 3;
              }));
        },
        a = [];
    },
    6965: function (t, n, e) {
      "use strict";
      e.r(n);
      var o = e("47ab"),
        i = e("d66a");
      for (var a in i)
        ["default"].indexOf(a) < 0 &&
          (function (t) {
            e.d(n, t, function () {
              return i[t];
            });
          })(a);
      e("bf0a");
      var u = e("828b"),
        r = Object(u.a)(
          i.default,
          o.b,
          o.c,
          !1,
          null,
          "7493b875",
          null,
          !1,
          o.a,
          void 0,
        );
      n.default = r.exports;
    },
    "6a15": function (t, n, e) {
      "use strict";
      (function (t) {
        Object.defineProperty(n, "__esModule", { value: !0 }),
          (n.default = void 0);
        var o = e("4689"),
          i = {
            data: function () {
              return { started: 0, mode: 0, eChecked: !1, refuseChecked: !1 };
            },
            components: {
              navigation: function () {
                e.e("components/navigation/index")
                  .then(
                    function () {
                      return resolve(e("af9e"));
                    }.bind(null, e),
                  )
                  .catch(e.oe);
              },
              confirmModal: function () {
                e.e("components/confirm-modal/confirm-modal")
                  .then(
                    function () {
                      return resolve(e("4e5b"));
                    }.bind(null, e),
                  )
                  .catch(e.oe);
              },
            },
            computed: {
              StatusBar: function () {
                return this.$store.state.systemInfo.statusBarHeight;
              },
              CustomBar: function () {
                var n = t.getMenuButtonBoundingClientRect();
                return (
                  n.height +
                  2 * (n.top - (0 !== this.StatusBar ? this.StatusBar : 20)) +
                  2
                );
              },
              logonUserInfo: function () {
                return this.$store.state.logonUserInfo;
              },
            },
            methods: {
              headlestart: function (t) {
                t
                  ? (this.started = 1)
                  : ((this.started = 0),
                    (this.mode = 0),
                    (this.$refs.confirmModal.show = !0));
              },
              cancelbtn1: function () {
                (this.started = 1),
                  (this.eChecked = !1),
                  (this.$refs.confirmModal.show = !1);
              },
              pointConfirm: function () {
                this.eChecked
                  ? (this.saveSalaryConfig(), this.cancelbtn1())
                  : t.showToast({
                      icon: "none",
                      title: "请先点击「我已清楚」",
                    });
              },
              getInit: function () {
                var t = this;
                (0, o.getSalaryConfig)({}).then(function (n) {
                  (t.started = n.config.started), (t.mode = n.config.mode);
                });
              },
              submit: function () {
                if (this.started || 1 == this.started) {
                  if (0 == this.mode)
                    return void t.showToast({
                      title: "请选择一个工资统计方式",
                      duration: 2e3,
                      icon: "none",
                    });
                  this.saveSalaryConfig();
                } else
                  this.started && 0 != this.started
                    ? this.saveSalaryConfig()
                    : (this.$refs.confirmModal.show = !0);
              },
              saveSalaryConfig: function () {
                this.started ? (this.started = 1) : (this.started = 0),
                  (0, o.saveSalaryConfig)({
                    mode: this.mode,
                    started: this.started,
                  }).then(function (n) {
                    200 == n.code
                      ? t.showToast({
                          title: "保存成功",
                          icon: "none",
                          mask: !0,
                          success: function () {
                            setTimeout(function () {
                              t.navigateBack();
                            }, 500);
                          },
                        })
                      : t.showToast({ title: n.msg, icon: "none", mask: !0 });
                  });
              },
            },
            onLoad: function () {
              this.getInit();
            },
          };
        n.default = i;
      }).call(this, e("df3c").default);
    },
    "7c9c": function (t, n, e) {
      "use strict";
      (function (t, n) {
        var o = e("47a9");
        e("86d2"), o(e("3240"));
        var i = o(e("6965"));
        (t.__webpack_require_UNI_MP_PLUGIN__ = e), n(i.default);
      }).call(this, e("3223").default, e("df3c").createPage);
    },
    "9ee4": function (t, n, e) {},
    bf0a: function (t, n, e) {
      "use strict";
      var o = e("9ee4");
      e.n(o).a;
    },
    d66a: function (t, n, e) {
      "use strict";
      e.r(n);
      var o = e("6a15"),
        i = e.n(o);
      for (var a in o)
        ["default"].indexOf(a) < 0 &&
          (function (t) {
            e.d(n, t, function () {
              return o[t];
            });
          })(a);
      n.default = i.a;
    },
  },
  [["7c9c", "common/runtime", "common/vendor"]],
]);
