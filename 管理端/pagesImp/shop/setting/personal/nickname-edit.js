(global.webpackJsonp = global.webpackJsonp || []).push([
  ["pagesImp/shop/setting/personal/nickname-edit"],
  {
    "3ad3": function (t, n, e) {},
    b585: function (t, n, e) {
      "use strict";
      e.r(n);
      var o = e("d457"),
        u = e.n(o);
      for (var i in o)
        ["default"].indexOf(i) < 0 &&
          (function (t) {
            e.d(n, t, function () {
              return o[t];
            });
          })(i);
      n.default = u.a;
    },
    b7be: function (t, n, e) {
      "use strict";
      var o = e("3ad3");
      e.n(o).a;
    },
    b8af: function (t, n, e) {
      "use strict";
      (function (t, n) {
        var o = e("47a9");
        e("86d2"), o(e("3240"));
        var u = o(e("cf09"));
        (t.__webpack_require_UNI_MP_PLUGIN__ = e), n(u.default);
      }).call(this, e("3223").default, e("df3c").createPage);
    },
    cf09: function (t, n, e) {
      "use strict";
      e.r(n);
      var o = e("e3b4"),
        u = e("b585");
      for (var i in u)
        ["default"].indexOf(i) < 0 &&
          (function (t) {
            e.d(n, t, function () {
              return u[t];
            });
          })(i);
      e("b7be");
      var a = e("828b"),
        f = Object(a.a)(
          u.default,
          o.b,
          o.c,
          !1,
          null,
          "3fec6509",
          null,
          !1,
          o.a,
          void 0,
        );
      n.default = f.exports;
    },
    d457: function (t, n, e) {
      "use strict";
      (function (t) {
        Object.defineProperty(n, "__esModule", { value: !0 }),
          (n.default = void 0);
        var o = e("f24f"),
          u = {
            data: function () {
              return {
                staff: { staffName: "", staffUserid: 2 },
                saveBtnStyle: {},
                inputStyle: {
                  background: "#F5F5F5",
                  width: "540rpx",
                  minHeight: "35px",
                  paddingLeft: "30rpx",
                  margin: "0rpx 0rpx",
                  marginLeft: " 30rpx",
                  borderRadius: "17px",
                  color: "#7E7E7E",
                },
                errorType: ["toast"],
                rules: {
                  staffName: [
                    { required: !0, message: "姓名不能为空" },
                    { max: 20, message: "姓名不能超过20个字" },
                  ],
                },
                top: null,
                background: "#FFFFFF",
                title: "修改姓名",
              };
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
            },
            methods: {
              saveNickname: function () {
                var n = this;
                this.$refs.uForm.validate(function (e) {
                  if (!e) return !1;
                  console.log("验证通过"),
                    (0, o.updateMyInfo)(n.staff).then(function (n) {
                      200 == n.code &&
                        t.showToast({
                          title: "保存成功",
                          icon: "none",
                          success: function () {
                            setTimeout(function () {
                              t.navigateBack();
                            }, 1e3);
                          },
                        });
                    });
                });
              },
            },
            onReady: function () {
              this.$refs.uForm.setRules(this.rules);
            },
            onLoad: function (t) {
              console.log(t),
                t.staffUserid &&
                  ((this.staff.staffUserid = t.staffUserid),
                  (this.staff.staffName = t.staffName),
                  console.log(this.staff.staffName));
            },
          };
        n.default = u;
      }).call(this, e("df3c").default);
    },
    e3b4: function (t, n, e) {
      "use strict";
      e.d(n, "b", function () {
        return u;
      }),
        e.d(n, "c", function () {
          return i;
        }),
        e.d(n, "a", function () {
          return o;
        });
      var o = {
          uForm: function () {
            return e
              .e("uview-ui/components/u-form/u-form")
              .then(e.bind(null, "a809"));
          },
          uFormItem: function () {
            return Promise.all([
              e.e("common/vendor"),
              e.e("uview-ui/components/u-form-item/u-form-item"),
            ]).then(e.bind(null, "ec61"));
          },
          uInput: function () {
            return Promise.all([
              e.e("common/vendor"),
              e.e("uview-ui/components/u-input/u-input"),
            ]).then(e.bind(null, "b5ea"));
          },
          ffBottomLogo: function () {
            return e
              .e("components/ff-bottom-logo/ff-bottom-logo")
              .then(e.bind(null, "3111"));
          },
        },
        u = function () {
          this.$createElement;
          this._self._c;
        },
        i = [];
    },
  },
  [["b8af", "common/runtime", "common/vendor"]],
]);
