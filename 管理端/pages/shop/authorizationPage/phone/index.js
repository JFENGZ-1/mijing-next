(global.webpackJsonp = global.webpackJsonp || []).push([
  ["pages/shop/authorizationPage/phone/index"],
  {
    "0dee": function (e, n, t) {},
    1176: function (e, n, t) {
      "use strict";
      var a = t("0dee");
      t.n(a).a;
    },
    "156e": function (e, n, t) {
      "use strict";
      t.r(n);
      var a = t("2f04"),
        o = t("1903");
      for (var r in o)
        ["default"].indexOf(r) < 0 &&
          (function (e) {
            t.d(n, e, function () {
              return o[e];
            });
          })(r);
      t("1176"), t("5cfe");
      var i = t("828b"),
        c = Object(i.a)(
          o.default,
          a.b,
          a.c,
          !1,
          null,
          "8fb7980e",
          null,
          !1,
          a.a,
          void 0,
        );
      n.default = c.exports;
    },
    1903: function (e, n, t) {
      "use strict";
      t.r(n);
      var a = t("5293"),
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
    "2f04": function (e, n, t) {
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
    5293: function (e, n, t) {
      "use strict";
      (function (e) {
        Object.defineProperty(n, "__esModule", { value: !0 }),
          (n.default = void 0);
        var a = t("1ba0"),
          o = {
            data: function () {
              return {
                avatarUrl: "",
                nickname: "",
                showUrl: "",
                sign: "",
                URLsource: "",
              };
            },
            onLoad: function (n) {
              var t = this;
              e.getStorage({
                key: "authorizationInfo",
                success: function (e) {
                  var n = e.data,
                    a = n.avatarUrl,
                    o = n.nickname,
                    r = n.showUrl;
                  (t.avatarUrl = a), (t.nickname = o), (t.showUrl = r);
                },
              }),
                n && n.source && (this.URLsource = n.source);
            },
            methods: {
              getPhoneNumber: function (n) {
                var t = this,
                  o = n.detail.code;
                o
                  ? (0, a.getWeixinPhoneNumber)({ code: o, gztype: 2 }).then(
                      function (n) {
                        if (200 == n.code) {
                          var a = n.data.phone_info.purePhoneNumber,
                            o = t;
                          e.setStorage({
                            key: "authorizationInfo",
                            data: {
                              showUrl: o.showUrl,
                              avatarUrl: o.avatarUrl,
                              nickname: o.nickname,
                              userphone: a,
                            },
                            success: function (n) {
                              "visitor" == t.URLsource
                                ? e.reLaunch({ url: "/pages/home/home" })
                                : e.redirectTo({
                                    url: "/pagesImp/shop/setting/store/store-setting?id=storesManagement",
                                  });
                            },
                          });
                        } else
                          e.showToast({
                            title: "没有获取到手机号",
                            icon: "none",
                            mask: !0,
                          });
                      },
                    )
                  : e.showToast({
                      title: "没有获取到手机号",
                      icon: "none",
                      mask: !0,
                    });
              },
            },
          };
        n.default = o;
      }).call(this, t("df3c").default);
    },
    "5cfe": function (e, n, t) {
      "use strict";
      var a = t("69e2");
      t.n(a).a;
    },
    "69e2": function (e, n, t) {},
    bf2c: function (e, n, t) {
      "use strict";
      (function (e, n) {
        var a = t("47a9");
        t("86d2"), a(t("3240"));
        var o = a(t("156e"));
        (e.__webpack_require_UNI_MP_PLUGIN__ = t), n(o.default);
      }).call(this, t("3223").default, t("df3c").createPage);
    },
  },
  [["bf2c", "common/runtime", "common/vendor"]],
]);
