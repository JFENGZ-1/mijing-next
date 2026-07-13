(global.webpackJsonp = global.webpackJsonp || []).push([
  ["pages/receiveCard/authorization/info/index"],
  {
    "18a3": function (n, t, e) {},
    "25e5": function (n, t, e) {
      var a = e("18a3");
      e.n(a).a;
    },
    "3b12": function (n, t, e) {
      (function (n, t) {
        var a = e("47a9");
        e("9785"), a(e("3240"));
        var o = a(e("e015"));
        (n.__webpack_require_UNI_MP_PLUGIN__ = e), t(o.default);
      }).call(this, e("3223").default, e("df3c").createPage);
    },
    "3dc8": function (n, t, e) {
      e.d(t, "b", function () {
        return o;
      }),
        e.d(t, "c", function () {
          return i;
        }),
        e.d(t, "a", function () {
          return a;
        });
      var a = {
          uIcon: function () {
            return e
              .e("node-modules/uview-ui/components/u-icon/u-icon")
              .then(e.bind(null, "e4b0"));
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
    "4f1a": function (n, t, e) {
      e.r(t);
      var a = e("e5ee"),
        o = e.n(a);
      for (var i in a)
        ["default"].indexOf(i) < 0 &&
          (function (n) {
            e.d(t, n, function () {
              return a[n];
            });
          })(i);
      t.default = o.a;
    },
    "9db3": function (n, t, e) {},
    d54a: function (n, t, e) {
      var a = e("9db3");
      e.n(a).a;
    },
    e015: function (n, t, e) {
      e.r(t);
      var a = e("3dc8"),
        o = e("4f1a");
      for (var i in o)
        ["default"].indexOf(i) < 0 &&
          (function (n) {
            e.d(t, n, function () {
              return o[n];
            });
          })(i);
      e("25e5"), e("d54a");
      var c = e("828b"),
        r = Object(c.a)(
          o.default,
          a.b,
          a.c,
          !1,
          null,
          "7340d4c8",
          null,
          !1,
          a.a,
          void 0,
        );
      t.default = r.exports;
    },
    e5ee: function (n, t, e) {
      (function (n) {
        var a = e("47a9");
        Object.defineProperty(t, "__esModule", { value: !0 }),
          (t.default = void 0);
        var o = a(e("bd1e")),
          i = {
            data: function () {
              return { avatarUrl: "", nickname: "" };
            },
            components: {
              Privacy: function () {
                e.e("components/privacy/privacy")
                  .then(
                    function () {
                      return resolve(e("d373"));
                    }.bind(null, e),
                  )
                  .catch(e.oe);
              },
            },
            computed: {},
            onLoad: function () {
              n.hideHomeButton();
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
                      i = a.dbUrl;
                    n.setStorage({
                      key: "authorizationInfo",
                      data: { showUrl: o, faceurl: i, nickname: e.nickname },
                      success: function (t) {
                        n.redirectTo({
                          url: "/pages/receiveCard/authorization/phone/index",
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
  },
  [["3b12", "common/runtime", "common/vendor"]],
]);
