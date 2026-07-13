(global.webpackJsonp = global.webpackJsonp || []).push([
  ["pagesImp/shop/setting/personal/phone-edit"],
  {
    3542: function (t, e, n) {
      "use strict";
      n.r(e);
      var o = n("d268"),
        i = n.n(o);
      for (var u in o)
        ["default"].indexOf(u) < 0 &&
          (function (t) {
            n.d(e, t, function () {
              return o[t];
            });
          })(u);
      e.default = i.a;
    },
    "5edb": function (t, e, n) {
      "use strict";
      n.r(e);
      var o = n("fcd6"),
        i = n("3542");
      for (var u in i)
        ["default"].indexOf(u) < 0 &&
          (function (t) {
            n.d(e, t, function () {
              return i[t];
            });
          })(u);
      n("875a");
      var r = n("828b"),
        a = Object(r.a)(
          i.default,
          o.b,
          o.c,
          !1,
          null,
          "ad5c3722",
          null,
          !1,
          o.a,
          void 0,
        );
      e.default = a.exports;
    },
    "6fef": function (t, e, n) {},
    "875a": function (t, e, n) {
      "use strict";
      var o = n("6fef");
      n.n(o).a;
    },
    d268: function (t, e, n) {
      "use strict";
      (function (t) {
        Object.defineProperty(e, "__esModule", { value: !0 }),
          (e.default = void 0);
        var o = n("f24f"),
          i = n("1ba0"),
          u = {
            data: function () {
              var t = this;
              return {
                phone: "请输入手机号",
                staff: { staffUserid: "", staffTel: "" },
                savedis: !0,
                inputStyle: {
                  display: "none",
                  background: "#F5F5F5",
                  width: "540rpx",
                  minHeight: "35px",
                  paddingLeft: "30rpx",
                  margin: "0rpx 0rpx",
                  marginLeft: " 55rpx",
                  borderRadius: "17px",
                  color: "#7E7E7E",
                },
                errorType: ["toast"],
                top: null,
                background: "#FFFFFF",
                title: "修改手机号",
                rules: {
                  staffTel: [
                    { required: !0, message: "请输入手机号" },
                    {
                      validator: function (e, n, o) {
                        return !!/^(1[3-9]\d{9}|[0-9]{8})$/.test(
                          t.staff.staffTel,
                        );
                      },
                      message: "手机号码不正确",
                      trigger: ["change", "blur"],
                    },
                  ],
                  smsCode: [{ required: !0, message: "请输入验证码" }],
                },
                count: 0,
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
            },
            methods: {
              getPhoneNumber: function (e) {
                var n = this,
                  o = e.detail.code;
                console.log(e),
                  console.log(o + "               "),
                  o &&
                    (0, i.getWeixinPhoneNumber)({ code: o, gztype: 2 }).then(
                      function (e) {
                        200 == e.code
                          ? ((n.savedis = !1),
                            (n.phone = e.data.phone_info.purePhoneNumber),
                            (n.staff.staffTel =
                              e.data.phone_info.purePhoneNumber))
                          : t.showToast({
                              title: "获取手机号失败",
                              icon: "none",
                              mask: !0,
                            });
                      },
                    );
              },
              saveTel: function () {
                var e = this;
                this.phone == this.staff.staffTel
                  ? this.$refs.uForm.validate(function (n) {
                      if (!n) return !1;
                      console.log("验证通过"),
                        (0, o.updateMyInfo)(e.staff).then(function (e) {
                          200 == e.code &&
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
                    })
                  : t.showToast({
                      title: "输入手机号和发送短信手机号不一致",
                      icon: "none",
                    });
              },
            },
            onReady: function () {
              this.$refs.uForm.setRules(this.rules);
            },
            onLoad: function (t) {
              t.staffUserid && (this.staff.staffUserid = t.staffUserid);
            },
          };
        e.default = u;
      }).call(this, n("df3c").default);
    },
    f270: function (t, e, n) {
      "use strict";
      (function (t, e) {
        var o = n("47a9");
        n("86d2"), o(n("3240"));
        var i = o(n("5edb"));
        (t.__webpack_require_UNI_MP_PLUGIN__ = n), e(i.default);
      }).call(this, n("3223").default, n("df3c").createPage);
    },
    fcd6: function (t, e, n) {
      "use strict";
      n.d(e, "b", function () {
        return i;
      }),
        n.d(e, "c", function () {
          return u;
        }),
        n.d(e, "a", function () {
          return o;
        });
      var o = {
          uForm: function () {
            return n
              .e("uview-ui/components/u-form/u-form")
              .then(n.bind(null, "a809"));
          },
          uFormItem: function () {
            return Promise.all([
              n.e("common/vendor"),
              n.e("uview-ui/components/u-form-item/u-form-item"),
            ]).then(n.bind(null, "ec61"));
          },
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
          this._self._c;
        },
        u = [];
    },
  },
  [["f270", "common/runtime", "common/vendor"]],
]);
