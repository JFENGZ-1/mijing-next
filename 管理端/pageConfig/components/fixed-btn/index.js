require("../../common/vendor.js"),
  (global.webpackJsonp = global.webpackJsonp || []).push([
    ["pageConfig/components/fixed-btn/index"],
    {
      "04b21": function (n, e, t) {
        "use strict";
        var o = t("70da");
        t.n(o).a;
      },
      "2ef6": function (n, e, t) {
        "use strict";
        t.d(e, "b", function () {
          return o;
        }),
          t.d(e, "c", function () {
            return i;
          }),
          t.d(e, "a", function () {});
        var o = function () {
            this.$createElement;
            this._self._c;
          },
          i = [];
      },
      "394b": function (n, e, t) {
        "use strict";
        t.r(e);
        var o = t("7a90"),
          i = t.n(o);
        for (var c in o)
          ["default"].indexOf(c) < 0 &&
            (function (n) {
              t.d(e, n, function () {
                return o[n];
              });
            })(c);
        e.default = i.a;
      },
      "5f88": function (n, e, t) {
        "use strict";
        t.r(e);
        var o = t("2ef6"),
          i = t("394b");
        for (var c in i)
          ["default"].indexOf(c) < 0 &&
            (function (n) {
              t.d(e, n, function () {
                return i[n];
              });
            })(c);
        t("04b21");
        var f = t("828b"),
          a = Object(f.a)(
            i.default,
            o.b,
            o.c,
            !1,
            null,
            "f759d990",
            null,
            !1,
            o.a,
            void 0,
          );
        e.default = a.exports;
      },
      "70da": function (n, e, t) {},
      "7a90": function (n, e, t) {
        "use strict";
        Object.defineProperty(e, "__esModule", { value: !0 }),
          (e.default = void 0),
          (e.default = {
            methods: {
              Click: function () {
                this.$emit("Click");
              },
            },
          });
      },
    },
  ]),
  (global.webpackJsonp = global.webpackJsonp || []).push([
    "pageConfig/components/fixed-btn/index-create-component",
    {
      "pageConfig/components/fixed-btn/index-create-component": function (
        n,
        e,
        t,
      ) {
        t("df3c").createComponent(t("5f88"));
      },
    },
    [["pageConfig/components/fixed-btn/index-create-component"]],
  ]);
