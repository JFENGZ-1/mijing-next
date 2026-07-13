(global.webpackJsonp = global.webpackJsonp || []).push([
  ["pagesImp/shop/staff/invited-share"],
  {
    "0a12": function (t, n, e) {
      "use strict";
      e.d(n, "b", function () {
        return s;
      }),
        e.d(n, "c", function () {
          return o;
        }),
        e.d(n, "a", function () {
          return i;
        });
      var i = {
          ffBottomLogo: function () {
            return e
              .e("components/ff-bottom-logo/ff-bottom-logo")
              .then(e.bind(null, "3111"));
          },
        },
        s = function () {
          this.$createElement;
          var t = (this._self._c, this.imgsrc("/static/imgs/back_home.png")),
            n = this.staffone
              ? this.imgsrc("/static/imgs/invited-share.jpg")
              : null;
          this.$mp.data = Object.assign({}, { $root: { m0: t, m1: n } });
        },
        o = [];
    },
    "15cf": function (t, n, e) {
      "use strict";
      var i = e("8f40");
      e.n(i).a;
    },
    "29c9": function (t, n, e) {
      "use strict";
      var i = e("da04");
      e.n(i).a;
    },
    "377e": function (t, n, e) {
      "use strict";
      (function (t, i) {
        Object.defineProperty(n, "__esModule", { value: !0 }),
          (n.default = void 0);
        var s = e("f24f"),
          o = {
            data: function () {
              return {
                background: "#FFFFFF",
                title: "",
                staffone: null,
                sign: "",
                buttext: "转发给TA",
                option: {},
                staffuserid: "",
                status: 0,
              };
            },
            computed: {
              dictVal: function () {
                return this.$store.state.dictVal;
              },
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
            methods: {
              backHome: function () {
                t.reLaunch({ url: "/pages/start/index" });
              },
              yqinit: function () {},
              getPhoneNumber: function (n) {
                var e = this;
                "getPhoneNumber:ok" == n.target.errMsg &&
                  i.login({
                    success: function (i) {
                      if (i.code) {
                        var o = {};
                        (o.code = i.code),
                          (o.gztype = 2),
                          (0, s.getUnionId)(o).then(function (i) {
                            var o = {};
                            (o.phonecode = n.detail.code),
                              (o.unionid = i.data.unionid),
                              (o.openid = i.data.openid),
                              (o.sign = e.sign),
                              (0, s.acceptInvite)(o).then(function (n) {
                                200 == n.code
                                  ? t.showToast({
                                      title: "操作成功",
                                      icon: "none",
                                      success: function () {
                                        setTimeout(function () {
                                          var t = e.staffone.siteId;
                                          e.$store
                                            .dispatch("getStopInfo", t)
                                            .then(function (t) {
                                              e.href({
                                                url: "/pages/home/home",
                                                openType: "reLaunch",
                                              });
                                            });
                                        }, 1e3);
                                      },
                                    })
                                  : t.showToast({
                                      title: n.msg,
                                      icon: "none",
                                      success: function () {},
                                    });
                              });
                          });
                      } else console.log("登录失败！" + i.errMsg);
                    },
                  });
              },
              getInviteData: function () {
                var t = this,
                  n = {};
                (n.staffuserid = this.staffuserid),
                  (0, s.getInviteData)(n).then(function (n) {
                    (t.sign = n.sign), t.getUserInfoBySign();
                  });
              },
              getUserInfoBySign: function () {
                var n = this;
                t.login({
                  success: function (e) {
                    var i = { sign: n.sign, jscode: e.code };
                    (0, s.getUserInfoBySign)(i).then(function (e) {
                      (n.staffone = e),
                        1 != n.status || e.isHomeButton || t.hideHomeButton();
                    });
                  },
                });
              },
              accept: function () {
                t.setStorage({
                  key: "sign",
                  data: this.sign,
                  success: function (n) {
                    t.navigateTo({ url: "/pagesImp/authorization/info/index" });
                  },
                });
              },
            },
            onShareAppMessage: function (t) {
              var n = this.shareUrl || "";
              if ("button" === t.from)
                return {
                  path:
                    "/pagesImp/shop/staff/invited-share?sign=" +
                    this.sign +
                    "&status=1",
                  imageUrl: n,
                };
            },
            onLoad: function (t) {
              (this.option = t),
                1 == t.status
                  ? ((this.sign = t.sign),
                    (this.status = 1),
                    (this.buttext = "接受邀请"),
                    this.getUserInfoBySign(),
                    this.yqinit())
                  : ((this.staffuserid = t.staffuserid),
                    this.getInviteData(),
                    i.showShareMenu({
                      withShareTicket: !0,
                      menus: ["shareAppMessage"],
                    }));
            },
          };
        n.default = o;
      }).call(this, e("df3c").default, e("3223").default);
    },
    5864: function (t, n, e) {
      "use strict";
      e.r(n);
      var i = e("0a12"),
        s = e("cd43");
      for (var o in s)
        ["default"].indexOf(o) < 0 &&
          (function (t) {
            e.d(n, t, function () {
              return s[t];
            });
          })(o);
      e("15cf"), e("29c9");
      var a = e("828b"),
        u = Object(a.a)(
          s.default,
          i.b,
          i.c,
          !1,
          null,
          "d14676a4",
          null,
          !1,
          i.a,
          void 0,
        );
      n.default = u.exports;
    },
    "66c7": function (t, n, e) {
      "use strict";
      (function (t, n) {
        var i = e("47a9");
        e("86d2"), i(e("3240"));
        var s = i(e("5864"));
        (t.__webpack_require_UNI_MP_PLUGIN__ = e), n(s.default);
      }).call(this, e("3223").default, e("df3c").createPage);
    },
    "8f40": function (t, n, e) {},
    cd43: function (t, n, e) {
      "use strict";
      e.r(n);
      var i = e("377e"),
        s = e.n(i);
      for (var o in i)
        ["default"].indexOf(o) < 0 &&
          (function (t) {
            e.d(n, t, function () {
              return i[t];
            });
          })(o);
      n.default = s.a;
    },
    da04: function (t, n, e) {},
  },
  [["66c7", "common/runtime", "common/vendor"]],
]);
