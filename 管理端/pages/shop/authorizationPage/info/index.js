(global.webpackJsonp = global.webpackJsonp || []).push([
  ["pages/shop/authorizationPage/info/index"],
  {
    "194f": function (n, t, e) {
      "use strict";
      e.r(t);
      var a = e("2a5e"),
        o = e.n(a);
      for (var c in a)
        ["default"].indexOf(c) < 0 &&
          (function (n) {
            e.d(t, n, function () {
              return a[n];
            });
          })(c);
      t.default = o.a;
    },
    "27c6": function (n, t, e) {},
    2984: function (n, t, e) {},
    "2a5e": function (n, t, e) {
      "use strict";
      (function (n) {
        var a = e("47a9");
        Object.defineProperty(t, "__esModule", { value: !0 }),
          (t.default = void 0);
        var o = a(e("7502")),
          c = e("1ba0"),
          i = {
            data: function () {
              return {
                avatarUrl: "",
                nickname: "",
                usercount: "",
                URLsource: "",
              };
            },
            components: {
              Privacy: function () {
                e.e("components/privacy/privacy")
                  .then(
                    function () {
                      return resolve(e("0e46"));
                    }.bind(null, e),
                  )
                  .catch(e.oe);
              },
            },
            computed: {
              dictVal: function () {
                return this.$store.state.dictVal;
              },
            },
            onLoad: function (n) {
              var t = this;
              n && n.source && (this.URLsource = n.source),
                (0, c.aduserCount)().then(function (n) {
                  t.usercount = n.usercount;
                });
            },
            methods: {
              guestToHome: function () {
                n.reLaunch({ url: "/pages/home/home" });
              },
              onChooseAvatar: function (n) {
                var t = n.detail.avatarUrl;
                console.log(n), (this.avatarUrl = t);
              },
              nicknameChange: function (n) {
                var t = n.detail.value;
                this.nickname = t;
              },
              confirm: function (t) {
                var e = this;
                try {
                  if ("" == this.avatarUrl) throw "请选择头像";
                  if ("" == this.nickname) throw "请输入昵称";
                } catch (t) {
                  return n.showToast({ title: t, icon: "none", mask: !0 }), !1;
                }
                n.uploadFile({
                  url: "".concat(o.default.baseUrl, "/common/uploadfile"),
                  filePath: this.avatarUrl,
                  name: "file",
                  complete: function (t) {
                    var a = JSON.parse(t.data),
                      o = a.webUrl,
                      c = a.dbUrl;
                    n.setStorage({
                      key: "authorizationInfo",
                      data: { avatarUrl: c, showUrl: o, nickname: e.nickname },
                      success: function (t) {
                        n.redirectTo({
                          url: "/pages/shop/authorizationPage/phone/index".concat(
                            "" !== e.URLsource ? "?source=" + e.URLsource : "",
                          ),
                        });
                      },
                    });
                  },
                  fail: function (t) {
                    n.showToast({ title: "上传失败", icon: "none" });
                  },
                });
              },
              login: function () {
                n.getUserProfile({
                  desc: "获取用户信息",
                  success: function (n) {
                    console.log(n);
                  },
                  fail: function () {
                    n.showToast({ title: "微信登录授权失败", icon: "none" });
                  },
                });
              },
            },
          };
        t.default = i;
      }).call(this, e("df3c").default);
    },
    "37db": function (n, t, e) {
      "use strict";
      (function (n, t) {
        var a = e("47a9");
        e("86d2"), a(e("3240"));
        var o = a(e("ca77"));
        (n.__webpack_require_UNI_MP_PLUGIN__ = e), t(o.default);
      }).call(this, e("3223").default, e("df3c").createPage);
    },
    "57a9": function (n, t, e) {
      "use strict";
      var a = e("27c6");
      e.n(a).a;
    },
    "867a": function (n, t, e) {
      "use strict";
      var a = e("2984");
      e.n(a).a;
    },
    "86f8": function (n, t, e) {
      "use strict";
      e.d(t, "b", function () {
        return o;
      }),
        e.d(t, "c", function () {
          return c;
        }),
        e.d(t, "a", function () {
          return a;
        });
      var a = {
          uIcon: function () {
            return e
              .e("uview-ui/components/u-icon/u-icon")
              .then(e.bind(null, "81af"));
          },
        },
        o = function () {
          this.$createElement;
          this._self._c;
        },
        c = [];
    },
    ca77: function (n, t, e) {
      "use strict";
      e.r(t);
      var a = e("86f8"),
        o = e("194f");
      for (var c in o)
        ["default"].indexOf(c) < 0 &&
          (function (n) {
            e.d(t, n, function () {
              return o[n];
            });
          })(c);
      e("867a"), e("57a9");
      var i = e("828b"),
        r = Object(i.a)(
          o.default,
          a.b,
          a.c,
          !1,
          null,
          "9c1189dc",
          null,
          !1,
          a.a,
          void 0,
        );
      t.default = r.exports;
    },
  },
  [["37db", "common/runtime", "common/vendor"]],
]);
