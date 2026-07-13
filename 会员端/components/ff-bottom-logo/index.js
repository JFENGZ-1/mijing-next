(global.webpackJsonp = global.webpackJsonp || []).push([
  ["components/ff-bottom-logo/index"],
  {
    "0f25": function (n, e, o) {
      o.r(e);
      var t = o("de95"),
        f = o("af6a");
      for (var a in f)
        ["default"].indexOf(a) < 0 &&
          (function (n) {
            o.d(e, n, function () {
              return f[n];
            });
          })(a);
      o("1dcb");
      var c = o("828b"),
        u = Object(c.a)(
          f.default,
          t.b,
          t.c,
          !1,
          null,
          "64082970",
          null,
          !1,
          t.a,
          void 0,
        );
      e.default = u.exports;
    },
    "19cf": function (n, e, o) {
      (function (n) {
        Object.defineProperty(e, "__esModule", { value: !0 }),
          (e.default = void 0);
        var o = {
          props: {
            dataType: {
              type: String,
              default: function () {
                return "1";
              },
            },
          },
          methods: {
            openPage: function () {
              n.navigateTo({ url: "/pages/webView/index" });
            },
          },
        };
        e.default = o;
      }).call(this, o("df3c").default);
    },
    "1dcb": function (n, e, o) {
      var t = o("e28e");
      o.n(t).a;
    },
    af6a: function (n, e, o) {
      o.r(e);
      var t = o("19cf"),
        f = o.n(t);
      for (var a in t)
        ["default"].indexOf(a) < 0 &&
          (function (n) {
            o.d(e, n, function () {
              return t[n];
            });
          })(a);
      e.default = f.a;
    },
    de95: function (n, e, o) {
      o.d(e, "b", function () {
        return t;
      }),
        o.d(e, "c", function () {
          return f;
        }),
        o.d(e, "a", function () {});
      var t = function () {
          this.$createElement;
          this._self._c;
        },
        f = [];
    },
    e28e: function (n, e, o) {},
  },
]),
  (global.webpackJsonp = global.webpackJsonp || []).push([
    "components/ff-bottom-logo/index-create-component",
    {
      "components/ff-bottom-logo/index-create-component": function (n, e, o) {
        o("df3c").createComponent(o("0f25"));
      },
    },
    [["components/ff-bottom-logo/index-create-component"]],
  ]);
