(global.webpackJsonp = global.webpackJsonp || []).push([
  ["components/privacy/privacy"],
  {
    "0e46": function (n, t, c) {
      "use strict";
      c.r(t);
      var a = c("4964"),
        i = c("2b06");
      for (var e in i)
        ["default"].indexOf(e) < 0 &&
          (function (n) {
            c.d(t, n, function () {
              return i[n];
            });
          })(e);
      c("d77a");
      var o = c("828b"),
        r = Object(o.a)(
          i.default,
          a.b,
          a.c,
          !1,
          null,
          "275d1021",
          null,
          !1,
          a.a,
          void 0,
        );
      t.default = r.exports;
    },
    "13a3": function (n, t, c) {
      "use strict";
      (function (n, c) {
        Object.defineProperty(t, "__esModule", { value: !0 }),
          (t.default = void 0);
        var a = {
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
              c.showModal({
                content: "拒绝后将无法识别并存储您的信息，点击确定后将退出",
                success: function (t) {
                  t.confirm && ((n.showPrivacy = !1), c.exitMiniProgram());
                },
              });
            },
            openPrivacyContract: function () {
              n.openPrivacyContract({
                fail: function () {
                  c.showToast({ title: "网络错误，请重试", icon: "error" });
                },
              });
            },
          },
        };
        t.default = a;
      }).call(this, c("3223").default, c("df3c").default);
    },
    "2b06": function (n, t, c) {
      "use strict";
      c.r(t);
      var a = c("13a3"),
        i = c.n(a);
      for (var e in a)
        ["default"].indexOf(e) < 0 &&
          (function (n) {
            c.d(t, n, function () {
              return a[n];
            });
          })(e);
      t.default = i.a;
    },
    4964: function (n, t, c) {
      "use strict";
      c.d(t, "b", function () {
        return a;
      }),
        c.d(t, "c", function () {
          return i;
        }),
        c.d(t, "a", function () {});
      var a = function () {
          this.$createElement;
          this._self._c;
        },
        i = [];
    },
    c343: function (n, t, c) {},
    d77a: function (n, t, c) {
      "use strict";
      var a = c("c343");
      c.n(a).a;
    },
  },
]),
  (global.webpackJsonp = global.webpackJsonp || []).push([
    "components/privacy/privacy-create-component",
    {
      "components/privacy/privacy-create-component": function (n, t, c) {
        c("df3c").createComponent(c("0e46"));
      },
    },
    [["components/privacy/privacy-create-component"]],
  ]);
