(global.webpackJsonp = global.webpackJsonp || []).push([
  ["pages/authorization/phone/index"],
  {
    "0cd6": function (e, n, t) {
      t.r(n);
      var o = t("d04d"),
        a = t.n(o);
      for (var r in o)
        ["default"].indexOf(r) < 0 &&
          (function (e) {
            t.d(n, e, function () {
              return o[e];
            });
          })(r);
      n.default = a.a;
    },
    5363: function (e, n, t) {},
    "717d": function (e, n, t) {
      t.d(n, "b", function () {
        return o;
      }),
        t.d(n, "c", function () {
          return a;
        }),
        t.d(n, "a", function () {});
      var o = function () {
          this.$createElement;
          this._self._c;
        },
        a = [];
    },
    9268: function (e, n, t) {
      t.r(n);
      var o = t("717d"),
        a = t("0cd6");
      for (var r in a)
        ["default"].indexOf(r) < 0 &&
          (function (e) {
            t.d(n, e, function () {
              return a[e];
            });
          })(r);
      t("d350"), t("ca0a");
      var i = t("828b"),
        c = Object(i.a)(
          a.default,
          o.b,
          o.c,
          !1,
          null,
          "54700299",
          null,
          !1,
          o.a,
          void 0,
        );
      n.default = c.exports;
    },
    acb5: function (e, n, t) {
      (function (e, n) {
        var o = t("47a9");
        t("9785"), o(t("3240"));
        var a = o(t("9268"));
        (e.__webpack_require_UNI_MP_PLUGIN__ = t), n(a.default);
      }).call(this, t("3223").default, t("df3c").createPage);
    },
    ca0a: function (e, n, t) {
      var o = t("ed01");
      t.n(o).a;
    },
    d04d: function (e, n, t) {
      (function (e) {
        var o = t("47a9");
        Object.defineProperty(n, "__esModule", { value: !0 }),
          (n.default = void 0);
        var a = o(t("7ca3")),
          r = t("f46d");
        function i(e, n) {
          var t = Object.keys(e);
          if (Object.getOwnPropertySymbols) {
            var o = Object.getOwnPropertySymbols(e);
            n &&
              (o = o.filter(function (n) {
                return Object.getOwnPropertyDescriptor(e, n).enumerable;
              })),
              t.push.apply(t, o);
          }
          return t;
        }
        function c(e) {
          for (var n = 1; n < arguments.length; n++) {
            var t = null != arguments[n] ? arguments[n] : {};
            n % 2
              ? i(Object(t), !0).forEach(function (n) {
                  (0, a.default)(e, n, t[n]);
                })
              : Object.getOwnPropertyDescriptors
                ? Object.defineProperties(
                    e,
                    Object.getOwnPropertyDescriptors(t),
                  )
                : i(Object(t)).forEach(function (n) {
                    Object.defineProperty(
                      e,
                      n,
                      Object.getOwnPropertyDescriptor(t, n),
                    );
                  });
          }
          return e;
        }
        var s = {
          data: function () {
            return { avatarUrl: "", nickname: "", showUrl: "" };
          },
          onLoad: function () {
            var n = this;
            e.getStorage({
              key: "authorizationInfo",
              success: function (e) {
                var t = e.data,
                  o = t.avatarUrl,
                  a = t.nickname,
                  r = t.showUrl;
                (n.avatarUrl = o), (n.nickname = a), (n.showUrl = r);
              },
            });
          },
          methods: {
            memberRegister: function (n) {
              var t = e.getStorageSync("siteId"),
                o = t ? c(c({}, n), {}, { siteid: t }) : n,
                a = this;
              e.showLoading({ title: "加载中", mask: !0 }),
                (0, r.register)(o).then(function (n) {
                  200 == n.code
                    ? (e.hideLoading(),
                      e.showToast({
                        title: "注册成功",
                        icon: "none",
                        success: function () {
                          a.$store.commit("SET_USERINFO", n);
                          var t = e.getStorageSync("skipDate"),
                            o = "";
                          if (t && t.go && t.siteId)
                            if (1 == t.go) o = "/pages/mine/index";
                            else if (3 == t.go) {
                              if (!t.c)
                                return (
                                  e.showToast({
                                    title: "未找到drainerId",
                                    icon: "none",
                                    mask: !0,
                                  }),
                                  !1
                                );
                              a.$store.dispatch("getAppointmentsParam", {}),
                                (o =
                                  "/pageCourse/coachCourse/index?drainerId=".concat(
                                    t.c,
                                  ));
                            } else if (2 == t.go) {
                              if (!t.c)
                                return (
                                  e.showToast({
                                    title: "未找到arrangeId",
                                    icon: "none",
                                    mask: !0,
                                  }),
                                  !1
                                );
                              a.$store.dispatch("getAppointmentsParam", {
                                dataid: t.c,
                                dataidType: 0,
                              }),
                                (o =
                                  "/pageCourse/clusterCourse/index?arrangeId=".concat(
                                    t.c,
                                  ));
                            } else o = "/pages/index/index";
                          else o = "/pages/index/index";
                          setTimeout(function () {
                            e.reLaunch({ url: o });
                          }, 1e3);
                        },
                      }))
                    : 560 == n.code
                      ? (e.hideLoading(),
                        e.reLaunch({
                          url:
                            "/pages/authorization/noLogin/index?siteInfo=" +
                            encodeURIComponent(JSON.stringify(n.siteInfo)),
                        }))
                      : 210 == n.code
                        ? (e.hideLoading(),
                          e.reLaunch({ url: "/pages/not/index" }))
                        : (e.hideLoading(),
                          e.showToast({ title: n.msg, icon: "none" }));
                });
            },
            getPhoneNumber: function (n) {
              var t = this,
                o = n.detail.code;
              o
                ? (0, r.getWeixinPhoneNumber)({ code: o, gztype: 3 }).then(
                    function (n) {
                      if (200 == n.code) {
                        var o = n.data.phone_info.purePhoneNumber,
                          a = t;
                        e.setStorage({
                          key: "authorizationInfo",
                          data: {
                            showUrl: a.showUrl,
                            avatarUrl: a.avatarUrl,
                            nickname: a.nickname,
                            userphone: o,
                            phonecode: n.phonecode,
                          },
                          success: function (e) {
                            a.memberRegister({
                              faceurl: a.avatarUrl,
                              nickname: a.nickname,
                              userphone: o,
                              phonecode: n.phonecode,
                            });
                          },
                        });
                      } else
                        e.showToast({
                          title: "未取得授权，请重试",
                          icon: "none",
                          mask: !0,
                        });
                    },
                  )
                : e.showToast({
                    title: "未取得授权，请重试",
                    icon: "none",
                    mask: !0,
                  });
            },
          },
        };
        n.default = s;
      }).call(this, t("df3c").default);
    },
    d350: function (e, n, t) {
      var o = t("5363");
      t.n(o).a;
    },
    ed01: function (e, n, t) {},
  },
  [["acb5", "common/runtime", "common/vendor"]],
]);
