(global.webpackJsonp = global.webpackJsonp || []).push([
  ["pageServer/serve-agreement"],
  {
    6982: function (n, t, e) {},
    8511: function (n, t, e) {},
    a3b4: function (n, t, e) {
      "use strict";
      Object.defineProperty(t, "__esModule", { value: !0 }),
        (t.default = void 0);
      var u = e("6b61"),
        a = {
          data: function () {
            return { nodes: null };
          },
          computed: {
            stopServeInfo: function () {
              return this.$store.state.stopServeInfo;
            },
          },
          onLoad: function () {
            var n = this;
            (0, u.getAgreement)({}).then(function (t) {
              n.nodes = t.data;
            });
          },
        };
      t.default = a;
    },
    b0f4: function (n, t, e) {
      "use strict";
      e.d(t, "b", function () {
        return u;
      }),
        e.d(t, "c", function () {
          return a;
        }),
        e.d(t, "a", function () {});
      var u = function () {
          this.$createElement;
          this._self._c;
        },
        a = [];
    },
    b136: function (n, t, e) {
      "use strict";
      e.r(t);
      var u = e("b0f4"),
        a = e("f7aa");
      for (var r in a)
        ["default"].indexOf(r) < 0 &&
          (function (n) {
            e.d(t, n, function () {
              return a[n];
            });
          })(r);
      e("b7df"), e("d826");
      var o = e("828b"),
        f = Object(o.a)(
          a.default,
          u.b,
          u.c,
          !1,
          null,
          "95dfcc52",
          null,
          !1,
          u.a,
          void 0,
        );
      t.default = f.exports;
    },
    b7df: function (n, t, e) {
      "use strict";
      var u = e("8511");
      e.n(u).a;
    },
    d826: function (n, t, e) {
      "use strict";
      var u = e("6982");
      e.n(u).a;
    },
    e4702: function (n, t, e) {
      "use strict";
      (function (n, t) {
        var u = e("47a9");
        e("86d2"), u(e("3240"));
        var a = u(e("b136"));
        (n.__webpack_require_UNI_MP_PLUGIN__ = e), t(a.default);
      }).call(this, e("3223").default, e("df3c").createPage);
    },
    f7aa: function (n, t, e) {
      "use strict";
      e.r(t);
      var u = e("a3b4"),
        a = e.n(u);
      for (var r in u)
        ["default"].indexOf(r) < 0 &&
          (function (n) {
            e.d(t, n, function () {
              return u[n];
            });
          })(r);
      t.default = a.a;
    },
  },
  [["e4702", "common/runtime", "common/vendor"]],
]);
