(global.webpackJsonp = global.webpackJsonp || []).push([
  ["pages/receiveCard/authorization/phone/index"],
  {
    "146e": function (e, n, t) {},
    "19e4": function (e, n, t) {
      var a = t("146e");
      t.n(a).a;
    },
    "2e37": function (e, n, t) {
      var a = t("3df1");
      t.n(a).a;
    },
    "3df1": function (e, n, t) {},
    "4ab8": function (e, n, t) {
      t.r(n);
      var a = t("7f64"),
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
    "7f64": function (e, n, t) {
      (function (e) {
        var a = t("47a9");
        Object.defineProperty(n, "__esModule", { value: !0 }),
          (n.default = void 0);
        var o = a(t("7eb4")),
          r = a(t("ee10")),
          c = t("f46d"),
          i = {
            data: function () {
              return {
                avatarUrl: "",
                nickname: "",
                showUrl: "",
                userCardId: "",
                phoneNumber: "",
              };
            },
            onLoad: function () {
              var n = this;
              e.getStorage({
                key: "authorizationInfo",
                success: function (e) {
                  var t = e.data,
                    a = t.faceurl,
                    o = t.nickname,
                    r = t.showUrl;
                  (n.avatarUrl = a), (n.nickname = o), (n.showUrl = r);
                },
              });
            },
            methods: {
              getPhoneNumber: function (n) {
                var t = this,
                  a = n.detail.code;
                a
                  ? (0, c.getWeixinPhoneNumber)({ code: a, gztype: 3 }).then(
                      function (n) {
                        if (200 == n.code) {
                          var a = n.data.phone_info.purePhoneNumber,
                            i = t;
                          (i.phoneNumber = a),
                            e.login({
                              success: function (t) {
                                var u = t.code;
                                (function () {
                                  var t = (0, r.default)(
                                    o.default.mark(function t() {
                                      var r, d, f, s;
                                      return o.default.wrap(
                                        function (t) {
                                          for (;;)
                                            switch ((t.prev = t.next)) {
                                              case 0:
                                                return (
                                                  (t.next = 2),
                                                  (0, c.getUnionId)({
                                                    code: u,
                                                    gztype: 3,
                                                  })
                                                );
                                              case 2:
                                                return (
                                                  (r = t.sent),
                                                  (d =
                                                    e.getStorageSync("cardId")),
                                                  (f = {
                                                    faceurl: i.avatarUrl,
                                                    nickname: i.nickname,
                                                    userphone: a,
                                                    sharekey: d,
                                                    unionid: r.data.unionid,
                                                    openid: r.data.openid,
                                                    phonecode: n.phonecode,
                                                  }),
                                                  (t.next = 7),
                                                  (0, c.takeByuserCardId)(f)
                                                );
                                              case 7:
                                                if (
                                                  (200 == (s = t.sent).code
                                                    ? (e.showToast({
                                                        title: "领取成功",
                                                        icon: "none",
                                                      }),
                                                      i.$store.commit(
                                                        "SET_USERINFO",
                                                        s,
                                                      ),
                                                      setTimeout(function () {
                                                        e.reLaunch({
                                                          url: "/pages/index/index",
                                                        });
                                                      }, 1e3))
                                                    : (e.navigateBack({
                                                        delta: 1,
                                                      }),
                                                      e.$emit("errorCallback", {
                                                        phoneNumber: a,
                                                      })),
                                                  (t.prev = 9),
                                                  200 == r.code)
                                                ) {
                                                  t.next = 12;
                                                  break;
                                                }
                                                throw r.msg + "2";
                                              case 12:
                                                t.next = 17;
                                                break;
                                              case 14:
                                                (t.prev = 14),
                                                  (t.t0 = t.catch(9)),
                                                  e.showToast({
                                                    title: t.t0 + "1",
                                                    icon: "none",
                                                  });
                                              case 17:
                                              case "end":
                                                return t.stop();
                                            }
                                        },
                                        t,
                                        null,
                                        [[9, 14]],
                                      );
                                    }),
                                  );
                                  return function () {
                                    return t.apply(this, arguments);
                                  };
                                })()();
                              },
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
        n.default = i;
      }).call(this, t("df3c").default);
    },
    bc74: function (e, n, t) {
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
    ce5f: function (e, n, t) {
      t.r(n);
      var a = t("bc74"),
        o = t("4ab8");
      for (var r in o)
        ["default"].indexOf(r) < 0 &&
          (function (e) {
            t.d(n, e, function () {
              return o[e];
            });
          })(r);
      t("19e4"), t("2e37");
      var c = t("828b"),
        i = Object(c.a)(
          o.default,
          a.b,
          a.c,
          !1,
          null,
          "28f12c2c",
          null,
          !1,
          a.a,
          void 0,
        );
      n.default = i.exports;
    },
    dd8e: function (e, n, t) {
      (function (e, n) {
        var a = t("47a9");
        t("9785"), a(t("3240"));
        var o = a(t("ce5f"));
        (e.__webpack_require_UNI_MP_PLUGIN__ = t), n(o.default);
      }).call(this, t("3223").default, t("df3c").createPage);
    },
  },
  [["dd8e", "common/runtime", "common/vendor"]],
]);
