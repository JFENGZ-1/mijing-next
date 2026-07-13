(global.webpackJsonp = global.webpackJsonp || []).push([
  ["pages/authorization/info/index"],
  {
    "04e8": function (n, t, a) {
      a.r(t);
      var e = a("0772"),
        o = a("bc59");
      for (var i in o)
        ["default"].indexOf(i) < 0 &&
          (function (n) {
            a.d(t, n, function () {
              return o[n];
            });
          })(i);
      a("646b"), a("59df");
      var r = a("828b"),
        c = Object(r.a)(
          o.default,
          e.b,
          e.c,
          !1,
          null,
          "43b013f8",
          null,
          !1,
          e.a,
          void 0,
        );
      t.default = c.exports;
    },
    "0772": function (n, t, a) {
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
              .e("node-modules/uview-ui/components/u-icon/u-icon")
              .then(a.bind(null, "e4b0"));
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
    "59df": function (n, t, a) {
      var e = a("74b1");
      a.n(e).a;
    },
    "646b": function (n, t, a) {
      var e = a("64dc");
      a.n(e).a;
    },
    "64dc": function (n, t, a) {},
    "74b1": function (n, t, a) {},
    bc59: function (n, t, a) {
      a.r(t);
      var e = a("d66f"),
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
    d66f: function (n, t, a) {
      (function (n) {
        var e = a("47a9");
        Object.defineProperty(t, "__esModule", { value: !0 }),
          (t.default = void 0);
        var o = e(a("bd1e")),
          i = {
            data: function () {
              return { avatarUrl: "", nickname: "", usercount: "" };
            },
            components: {
              Privacy: function () {
                a.e("components/privacy/privacy")
                  .then(
                    function () {
                      return resolve(a("d373"));
                    }.bind(null, a),
                  )
                  .catch(a.oe);
              },
            },
            computed: {},
            onLoad: function (t) {
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
                try {
                  if ("" == this.avatarUrl) throw "请选择头像";
                  if ("" == this.nickname) throw "请输入昵称";
                } catch (t) {
                  return n.showToast({ title: t, icon: "none", mask: !0 }), !1;
                }
                var a = this;
                n.uploadFile({
                  url: "".concat(o.default.baseUrl, "/common/uploadfile"),
                  filePath: a.avatarUrl,
                  name: "file",
                  complete: function (t) {
                    var e = JSON.parse(t.data),
                      o = {
                        showUrl: e.webUrl,
                        avatarUrl: e.dbUrl,
                        nickname: a.nickname,
                      };
                    n.setStorage({
                      key: "authorizationInfo",
                      data: o,
                      success: function (t) {
                        n.redirectTo({
                          url: "/pages/authorization/phone/index",
                        });
                      },
                    });
                  },
                  fail: function (t) {
                    n.showToast({ title: "上传失败", icon: "none" });
                  },
                });
              },
            },
          };
        t.default = i;
      }).call(this, a("df3c").default);
    },
    e43e: function (n, t, a) {
      (function (n, t) {
        var e = a("47a9");
        a("9785"), e(a("3240"));
        var o = e(a("04e8"));
        (n.__webpack_require_UNI_MP_PLUGIN__ = a), t(o.default);
      }).call(this, a("3223").default, a("df3c").createPage);
    },
  },
  [["e43e", "common/runtime", "common/vendor"]],
]);
