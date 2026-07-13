(global.webpackJsonp = global.webpackJsonp || []).push([
  ["components/privacy/privacy"],
  {
    "5a7a": function (n, t, a) {
      (function (n, a) {
        Object.defineProperty(t, "__esModule", { value: !0 }),
          (t.default = void 0);
        var c = {
          name: "privacy",
          data: function () {
            return { privacyContractName: "", showPrivacy: !1 };
          },
          created: function () {
            var t = this;
            n.getPrivacySetting &&
              n.getPrivacySetting({
                success: function (n) {
                  n.needAuthorization
                    ? ((t.privacyContractName = n.privacyContractName),
                      (t.showPrivacy = !0))
                    : (t.showPrivacy = !1);
                },
                fail: function () {},
                complete: function () {},
              });
          },
          methods: {
            handleAgreePrivacyAuthorization: function () {
              this.showPrivacy = !1;
              var t = this;
              n.requirePrivacyAuthorize({
                success: function (n) {
                  t.showPrivacy = !1;
                },
              });
            },
            exitMiniProgram: function () {
              var n = this;
              a.showModal({
                content: "拒绝后将无法识别并存储您的信息，点击确定后将退出",
                success: function (t) {
                  t.confirm && ((n.showPrivacy = !1), a.exitMiniProgram());
                },
              });
            },
            openPrivacyContract: function () {
              n.openPrivacyContract({
                fail: function () {
                  a.showToast({ title: "网络错误，请重试", icon: "error" });
                },
              });
            },
          },
        };
        t.default = c;
      }).call(this, a("3223").default, a("df3c").default);
    },
    6579: function (n, t, a) {
      var c = a("e72a");
      a.n(c).a;
    },
    b104: function (n, t, a) {
      a.d(t, "b", function () {
        return c;
      }),
        a.d(t, "c", function () {
          return o;
        }),
        a.d(t, "a", function () {});
      var c = function () {
          this.$createElement;
          this._self._c;
        },
        o = [];
    },
    b936: function (n, t, a) {
      a.r(t);
      var c = a("5a7a"),
        o = a.n(c);
      for (var i in c)
        ["default"].indexOf(i) < 0 &&
          (function (n) {
            a.d(t, n, function () {
              return c[n];
            });
          })(i);
      t.default = o.a;
    },
    d373: function (n, t, a) {
      a.r(t);
      var c = a("b104"),
        o = a("b936");
      for (var i in o)
        ["default"].indexOf(i) < 0 &&
          (function (n) {
            a.d(t, n, function () {
              return o[n];
            });
          })(i);
      a("6579");
      var e = a("828b"),
        r = Object(e.a)(
          o.default,
          c.b,
          c.c,
          !1,
          null,
          "14250a61",
          null,
          !1,
          c.a,
          void 0,
        );
      t.default = r.exports;
    },
    e72a: function (n, t, a) {},
  },
]),
  (global.webpackJsonp = global.webpackJsonp || []).push([
    "components/privacy/privacy-create-component",
    {
      "components/privacy/privacy-create-component": function (n, t, a) {
        a("df3c").createComponent(a("d373"));
      },
    },
    [["components/privacy/privacy-create-component"]],
  ]);
