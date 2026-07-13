(global.webpackJsonp = global.webpackJsonp || []).push([
  ["pageServer/components/service"],
  {
    "0292": function (e, t, n) {
      "use strict";
      n.d(t, "b", function () {
        return o;
      }),
        n.d(t, "c", function () {
          return c;
        }),
        n.d(t, "a", function () {});
      var o = function () {
          this.$createElement;
          var e = (this._self._c, this.imgsrc("/static/imgs/copy.png"));
          this.$mp.data = Object.assign({}, { $root: { m0: e } });
        },
        c = [];
    },
    "185e": function (e, t, n) {
      "use strict";
      var o = n("e325");
      n.n(o).a;
    },
    "6f8e": function (e, t, n) {
      "use strict";
      (function (e) {
        Object.defineProperty(t, "__esModule", { value: !0 }),
          (t.default = void 0);
        var n = {
          data: function () {
            return {};
          },
          computed: {
            shopInfo: function () {
              return this.$store.state.stopInfo;
            },
            stopServeInfo: function () {
              return this.$store.state.stopServeInfo;
            },
          },
          methods: {
            copy: function () {
              var t = this;
              e.setClipboardData({
                data: this.stopServeInfo.customServicer,
                showToast: !1,
                success: function () {
                  e.hideToast(), t.$emit("copy");
                },
              });
            },
          },
        };
        t.default = n;
      }).call(this, n("df3c").default);
    },
    "851c": function (e, t, n) {
      "use strict";
      n.r(t);
      var o = n("6f8e"),
        c = n.n(o);
      for (var r in o)
        ["default"].indexOf(r) < 0 &&
          (function (e) {
            n.d(t, e, function () {
              return o[e];
            });
          })(r);
      t.default = c.a;
    },
    "9fa9b": function (e, t, n) {
      "use strict";
      n.r(t);
      var o = n("0292"),
        c = n("851c");
      for (var r in c)
        ["default"].indexOf(r) < 0 &&
          (function (e) {
            n.d(t, e, function () {
              return c[e];
            });
          })(r);
      n("185e");
      var a = n("828b"),
        s = Object(a.a)(
          c.default,
          o.b,
          o.c,
          !1,
          null,
          "fa3c4d88",
          null,
          !1,
          o.a,
          void 0,
        );
      t.default = s.exports;
    },
    e325: function (e, t, n) {},
  },
]),
  (global.webpackJsonp = global.webpackJsonp || []).push([
    "pageServer/components/service-create-component",
    {
      "pageServer/components/service-create-component": function (e, t, n) {
        n("df3c").createComponent(n("9fa9b"));
      },
    },
    [["pageServer/components/service-create-component"]],
  ]);
