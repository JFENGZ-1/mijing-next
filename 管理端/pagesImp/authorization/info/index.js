(global.webpackJsonp = global.webpackJsonp || []).push([
  ["pagesImp/authorization/info/index"],
  {
    "1f34": function (n, t, a) {
      "use strict";
      (function (n) {
        var e = a("47a9");
        Object.defineProperty(t, "__esModule", { value: !0 }),
          (t.default = void 0);
        var o = e(a("7502")),
          i = a("1ba0"),
          r = {
            data: function () {
              return { avatarUrl: "", nickname: "", usercount: "" };
            },
            components: {
              Privacy: function () {
                a.e("components/privacy/privacy")
                  .then(
                    function () {
                      return resolve(a("0e46"));
                    }.bind(null, a),
                  )
                  .catch(a.oe);
              },
            },
            computed: {},
            onLoad: function () {
              var n = this;
              (0, i.aduserCount)().then(function (t) {
                n.usercount = t.usercount;
              });
            },
            methods: {
              onChooseAvatar: function (n) {
                var t = n.detail.avatarUrl;
                this.avatarUrl = t;
              },
              nicknameChange: function (n) {
                var t = n.detail.value;
                this.nickname = t;
              },
              confirm: function (t) {
                var a = this;
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
                    var e = JSON.parse(t.data),
                      o = e.webUrl,
                      i = e.dbUrl;
                    n.setStorage({
                      key: "authorizationInfo",
                      data: {
                        avatarUrl: o,
                        avatarDBUrl: i,
                        nickname: a.nickname,
                      },
                      success: function (t) {
                        n.redirectTo({
                          url: "/pagesImp/authorization/phone/index",
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
        t.default = r;
      }).call(this, a("df3c").default);
    },
    2848: function (n, t, a) {
      "use strict";
      var e = a("84ab");
      a.n(e).a;
    },
    "3b4b": function (n, t, a) {
      "use strict";
      a.d(t, "b", function () {
        return o;
      }),
        a.d(t, "c", function () {
          return i;
        }),
        a.d(t, "a", function () {
          return e;
        });
      var e = {
          uIcon: function () {
            return a
              .e("uview-ui/components/u-icon/u-icon")
              .then(a.bind(null, "81af"));
          },
        },
        o = function () {
          this.$createElement;
          var n =
            (this._self._c,
            this.avatarUrl
              ? null
              : this.imgsrc("/static/imgs/default-photo.png"));
          this.$mp.data = Object.assign({}, { $root: { m0: n } });
        },
        i = [];
    },
    "5e0e": function (n, t, a) {
      "use strict";
      var e = a("f5e8");
      a.n(e).a;
    },
    "84ab": function (n, t, a) {},
    "8f81": function (n, t, a) {
      "use strict";
      a.r(t);
      var e = a("3b4b"),
        o = a("af51");
      for (var i in o)
        ["default"].indexOf(i) < 0 &&
          (function (n) {
            a.d(t, n, function () {
              return o[n];
            });
          })(i);
      a("2848"), a("5e0e");
      var r = a("828b"),
        c = Object(r.a)(
          o.default,
          e.b,
          e.c,
          !1,
          null,
          "30c40a40",
          null,
          !1,
          e.a,
          void 0,
        );
      t.default = c.exports;
    },
    af51: function (n, t, a) {
      "use strict";
      a.r(t);
      var e = a("1f34"),
        o = a.n(e);
      for (var i in e)
        ["default"].indexOf(i) < 0 &&
          (function (n) {
            a.d(t, n, function () {
              return e[n];
            });
          })(i);
      t.default = o.a;
    },
    e78fd: function (n, t, a) {
      "use strict";
      (function (n, t) {
        var e = a("47a9");
        a("86d2"), e(a("3240"));
        var o = e(a("8f81"));
        (n.__webpack_require_UNI_MP_PLUGIN__ = a), t(o.default);
      }).call(this, a("3223").default, a("df3c").createPage);
    },
    f5e8: function (n, t, a) {},
  },
  [["e78fd", "common/runtime", "common/vendor"]],
]);
