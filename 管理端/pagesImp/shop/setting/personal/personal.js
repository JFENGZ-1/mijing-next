(global.webpackJsonp = global.webpackJsonp || []).push([
  ["pagesImp/shop/setting/personal/personal"],
  {
    "01bbb": function (t, n, e) {
      "use strict";
      (function (t, n) {
        var o = e("47a9");
        e("86d2"), o(e("3240"));
        var a = o(e("de014"));
        (t.__webpack_require_UNI_MP_PLUGIN__ = e), n(a.default);
      }).call(this, e("3223").default, e("df3c").createPage);
    },
    "13a4": function (t, n, e) {
      "use strict";
      e.r(n);
      var o = e("4217"),
        a = e.n(o);
      for (var i in o)
        ["default"].indexOf(i) < 0 &&
          (function (t) {
            e.d(n, t, function () {
              return o[t];
            });
          })(i);
      n.default = a.a;
    },
    4217: function (t, n, e) {
      "use strict";
      (function (t) {
        var o = e("47a9");
        Object.defineProperty(n, "__esModule", { value: !0 }),
          (n.default = void 0);
        var a = e("f24f"),
          i = o(e("7502")),
          u = {
            data: function () {
              return {
                staff: {},
                top: null,
                background: "#FFFFFF",
                title: "我的资料",
                showtip: !1,
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
            created: function () {
              var n = this;
              t.$on("uAvatarCropper", function (e) {
                (n.showtip = !0),
                  (n.avatar = e),
                  t.uploadFile({
                    url: "".concat(i.default.baseUrl, "/common/uploadfile"),
                    filePath: e,
                    name: "file",
                    complete: function (t) {
                      var e = JSON.parse(t.data).dbUrl,
                        o = JSON.parse(t.data).webUrl;
                      (n.staff.staffFace = o),
                        n.$forceUpdate(),
                        n.updateMyInfo(e);
                    },
                  });
              });
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
              updateMyInfo: function (n) {
                var e = {};
                (e.staffFace = n),
                  (e.staffUserid = this.staff.staffUserid),
                  (0, a.updateMyInfo)(e).then(function (n) {
                    t.showToast({
                      title: "保存头像成功",
                      icon: "none",
                      mask: !0,
                    });
                  });
              },
              chooseAvatar: function () {
                this.$u.route({
                  url: "/uview-ui/components/u-avatar-cropper/u-avatar-cropper",
                  params: { destWidth: 220, rectWidth: 350, fileType: "jpg" },
                });
              },
              loadUserOne: function () {
                var t = this;
                (0, a.getMyInfo)().then(function (n) {
                  t.staff = n.staff;
                });
              },
              loginout: function () {
                (0, a.loginout)().then(function (n) {
                  t.showModal({
                    title: "提示",
                    showCancel: !1,
                    content: "退出登录成功！",
                    success: function (n) {
                      n.confirm && t.reLaunch({ url: "/pages/login/login" });
                    },
                  });
                });
              },
            },
            onShow: function () {
              this.showtip || this.loadUserOne(), (this.showtip = !1);
            },
            onUnload: function () {
              t.$off("uAvatarCropper");
            },
          };
        n.default = u;
      }).call(this, e("df3c").default);
    },
    5143: function (t, n, e) {
      "use strict";
      var o = e("7b69");
      e.n(o).a;
    },
    "7b69": function (t, n, e) {},
    de014: function (t, n, e) {
      "use strict";
      e.r(n);
      var o = e("e1d1"),
        a = e("13a4");
      for (var i in a)
        ["default"].indexOf(i) < 0 &&
          (function (t) {
            e.d(n, t, function () {
              return a[t];
            });
          })(i);
      e("5143");
      var u = e("828b"),
        r = Object(u.a)(
          a.default,
          o.b,
          o.c,
          !1,
          null,
          "af11577a",
          null,
          !1,
          o.a,
          void 0,
        );
      n.default = r.exports;
    },
    e1d1: function (t, n, e) {
      "use strict";
      e.d(n, "b", function () {
        return a;
      }),
        e.d(n, "c", function () {
          return i;
        }),
        e.d(n, "a", function () {
          return o;
        });
      var o = {
          uIcon: function () {
            return e
              .e("uview-ui/components/u-icon/u-icon")
              .then(e.bind(null, "81af"));
          },
          uLine: function () {
            return e
              .e("uview-ui/components/u-line/u-line")
              .then(e.bind(null, "fac3"));
          },
          ffBottomLogo: function () {
            return e
              .e("components/ff-bottom-logo/ff-bottom-logo")
              .then(e.bind(null, "3111"));
          },
        },
        a = function () {
          this.$createElement;
          var t =
              (this._self._c,
              this.staff.staffFace
                ? null
                : this.imgsrc("/static/imgs/headimg.png")),
            n = this.imgsrc("/static/imgs/camera.png");
          this.$mp.data = Object.assign({}, { $root: { m0: t, m1: n } });
        },
        i = [];
    },
  },
  [["01bbb", "common/runtime", "common/vendor"]],
]);
