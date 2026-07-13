(global.webpackJsonp = global.webpackJsonp || []).push([
  ["components/ff-bottom-logo/showLogo"],
  {
    "00f8": function (o, n, t) {
      var e = t("6cda");
      t.n(e).a;
    },
    "1d8b": function (o, n, t) {
      (function (o) {
        Object.defineProperty(n, "__esModule", { value: !0 }),
          (n.default = void 0);
        var t = {
          props: {},
          methods: {
            openPage: function () {
              o.navigateTo({ url: "/pages/webView/index" });
            },
          },
        };
        n.default = t;
      }).call(this, t("df3c").default);
    },
    "6cda": function (o, n, t) {},
    b289: function (o, n, t) {
      t.d(n, "b", function () {
        return e;
      }),
        t.d(n, "c", function () {
          return a;
        }),
        t.d(n, "a", function () {});
      var e = function () {
          this.$createElement;
          var o = (this._self._c, this.imgsrc("/static/imgs/bottom_logo.png"));
          this.$mp.data = Object.assign({}, { $root: { m0: o } });
        },
        a = [];
    },
    d908: function (o, n, t) {
      t.r(n);
      var e = t("1d8b"),
        a = t.n(e);
      for (var c in e)
        ["default"].indexOf(c) < 0 &&
          (function (o) {
            t.d(n, o, function () {
              return e[o];
            });
          })(c);
      n.default = a.a;
    },
    dab2: function (o, n, t) {
      t.r(n);
      var e = t("b289"),
        a = t("d908");
      for (var c in a)
        ["default"].indexOf(c) < 0 &&
          (function (o) {
            t.d(n, o, function () {
              return a[o];
            });
          })(c);
      t("00f8");
      var f = t("828b"),
        i = Object(f.a)(
          a.default,
          e.b,
          e.c,
          !1,
          null,
          "ab338e24",
          null,
          !1,
          e.a,
          void 0,
        );
      n.default = i.exports;
    },
  },
]),
  (global.webpackJsonp = global.webpackJsonp || []).push([
    "components/ff-bottom-logo/showLogo-create-component",
    {
      "components/ff-bottom-logo/showLogo-create-component": function (
        o,
        n,
        t,
      ) {
        t("df3c").createComponent(t("dab2"));
      },
    },
    [["components/ff-bottom-logo/showLogo-create-component"]],
  ]);
