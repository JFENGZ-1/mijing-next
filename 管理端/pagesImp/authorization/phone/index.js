(global.webpackJsonp = global.webpackJsonp || []).push([
  ["pagesImp/authorization/phone/index"],
  {
    2850: function (e, n, t) {},
    3410: function (e, n, t) {
      "use strict";
      t.r(n);
      var a = t("d33a"),
        o = t.n(a);
      for (var r in a)
        ["default"].indexOf(r) < 0 &&
          (function (e) {
            t.d(n, e, function () {
              return a[e];
            });
          })(r);
      n.default = o.a;
    },
    "3e7d": function (e, n, t) {},
    b032: function (e, n, t) {
      "use strict";
      (function (e, n) {
        var a = t("47a9");
        t("86d2"), a(t("3240"));
        var o = a(t("bed6"));
        (e.__webpack_require_UNI_MP_PLUGIN__ = t), n(o.default);
      }).call(this, t("3223").default, t("df3c").createPage);
    },
    bed6: function (e, n, t) {
      "use strict";
      t.r(n);
      var a = t("ccba"),
        o = t("3410");
      for (var r in o)
        ["default"].indexOf(r) < 0 &&
          (function (e) {
            t.d(n, e, function () {
              return o[e];
            });
          })(r);
      t("e6fe"), t("d233");
      var i = t("828b"),
        c = Object(i.a)(
          o.default,
          a.b,
          a.c,
          !1,
          null,
          "80c17f42",
          null,
          !1,
          a.a,
          void 0,
        );
      n.default = c.exports;
    },
    ccba: function (e, n, t) {
      "use strict";
      t.d(n, "b", function () {
        return a;
      }),
        t.d(n, "c", function () {
          return o;
        }),
        t.d(n, "a", function () {});
      var a = function () {
          this.$createElement;
          this._self._c;
        },
        o = [];
    },
    d233: function (e, n, t) {
      "use strict";
      var a = t("2850");
      t.n(a).a;
    },
    d33a: function (e, n, t) {
      "use strict";
      (function (e) {
        var a = t("47a9");
        Object.defineProperty(n, "__esModule", { value: !0 }),
          (n.default = void 0);
        var o = a(t("7eb4")),
          r = a(t("ee10")),
          i = t("1ba0"),
          c = {
            data: function () {
              return { avatarUrl: "", nickname: "", avatarDBUrl: "", sign: "" };
            },
            onLoad: function () {
              var n = this;
              e.getStorage({
                key: "authorizationInfo",
                success: function (e) {
                  var t = e.data,
                    a = t.avatarUrl,
                    o = t.nickname,
                    r = t.avatarDBUrl;
                  (n.avatarUrl = a), (n.nickname = o), (n.avatarDBUrl = r);
                },
              }),
                e.getStorage({
                  key: "sign",
                  success: function (e) {
                    var t = e.data;
                    n.sign = t;
                  },
                });
            },
            methods: {
              getPhoneNumber: function (n) {
                var t = this,
                  a = n.detail.code;
                a
                  ? (0, i.getWeixinPhoneNumber)({ code: a, gztype: 2 }).then(
                      function (n) {
                        if (200 == n.code) {
                          var a = n.data.phone_info.purePhoneNumber,
                            c = t;
                          e.login({
                            success: (function () {
                              var n = (0, r.default)(
                                o.default.mark(function n(t) {
                                  var r, u, s, f, d, l, v;
                                  return o.default.wrap(function (n) {
                                    for (;;)
                                      switch ((n.prev = n.next)) {
                                        case 0:
                                          return (
                                            (r = t.code),
                                            (n.next = 3),
                                            (0, i.getUnionId)({
                                              code: r,
                                              gztype: 2,
                                            })
                                          );
                                        case 3:
                                          if (200 != (u = n.sent).code) {
                                            n.next = 14;
                                            break;
                                          }
                                          return (
                                            (s = u.data),
                                            (f = s.openid),
                                            (d = s.unionid),
                                            (l = {
                                              sign: c.sign,
                                              userphone: a,
                                              faceurl: c.avatarDBUrl,
                                              avatarUrl: c.avatarUrl,
                                              nickname: c.nickname,
                                              unionid: d,
                                              openid: f,
                                            }),
                                            console.log(l),
                                            (n.next = 10),
                                            (0, i.acceptInvite)(l)
                                          );
                                        case 10:
                                          200 == (v = n.sent).code
                                            ? e.setStorage({
                                                key: "authorizationInfo",
                                                data: {
                                                  avatarUrl: l.avatarUrl,
                                                  nickname: l.nickname,
                                                  userphone: l.userphone,
                                                },
                                                success: function (n) {
                                                  setTimeout(function () {
                                                    e.redirectTo({
                                                      url: "/pagesImp/authorization/success/index?siteName="
                                                        .concat(
                                                          v.userinfo.siteName,
                                                          "&staffType=",
                                                        )
                                                        .concat(
                                                          v.userinfo.staffType,
                                                        ),
                                                    });
                                                  }, 1e3);
                                                },
                                              })
                                            : 210 == v.code
                                              ? e.removeStorage({
                                                  key: "authorizationInfo",
                                                  success: function (n) {
                                                    e.redirectTo({
                                                      url: "/pagesImp/authorization/error/index?inputTel="
                                                        .concat(
                                                          v.inputTel,
                                                          "&staffTel=",
                                                        )
                                                        .concat(v.staffTel),
                                                    });
                                                  },
                                                })
                                              : (e.removeStorage({
                                                  key: "authorizationInfo",
                                                }),
                                                e.showToast({
                                                  title: v.msg,
                                                  icon: "none",
                                                  mask: !0,
                                                })),
                                            (n.next = 15);
                                          break;
                                        case 14:
                                          e.showToast({
                                            title: u.msg,
                                            icon: "none",
                                            mask: !0,
                                          });
                                        case 15:
                                        case "end":
                                          return n.stop();
                                      }
                                  }, n);
                                }),
                              );
                              return function (e) {
                                return n.apply(this, arguments);
                              };
                            })(),
                          });
                        } else
                          e.showToast({
                            title: "获取手机号失败",
                            icon: "none",
                            mask: !0,
                          });
                      },
                    )
                  : e.showToast({
                      title: "授权手机号失败",
                      icon: "none",
                      mask: !0,
                    });
              },
            },
          };
        n.default = c;
      }).call(this, t("df3c").default);
    },
    e6fe: function (e, n, t) {
      "use strict";
      var a = t("3e7d");
      t.n(a).a;
    },
  },
  [["b032", "common/runtime", "common/vendor"]],
]);
