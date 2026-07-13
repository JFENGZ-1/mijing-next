(global.webpackJsonp = global.webpackJsonp || []).push([
  ["pagesImp/components/ff-editor/ff-editor"],
  {
    "13b2": function (t, e, n) {
      "use strict";
      (function (t) {
        Object.defineProperty(e, "__esModule", { value: !0 }),
          (e.default = void 0);
        var n = {
          props: {
            popHeight: { type: String, default: "1200" },
            editorHeight: { type: String, default: "650" },
            maxLength: { type: Number, default: 150 },
          },
          data: function () {
            return {
              title: "",
              show: !1,
              placeholder: "请填写",
              blogContent: "",
              editorCtx: "",
              num: 0,
              tips: "",
              editorConter: "",
              flag: !0,
              coursetype: 6,
            };
          },
          methods: {
            onInput: function (t) {
              this.num = t.detail.text.replace(/<[^>]+>/g, "").length - 1;
            },
            open: function (t, e, n, o) {
              var i = this;
              i
                .createSelectorQuery()
                .select("#editor")
                .context(function (t) {
                  (i.editorCtx = t.context),
                    i.editorCtx.setContents({ html: i.editorConter });
                })
                .exec(),
                (this.title = n),
                (this.tips = o),
                (this.id = e),
                (this.editorConter = t),
                t && (this.num = t.replace(/<[^>]+>/g, "").length),
                (this.show = !0);
            },
            submit: function () {
              var e = this,
                n = this;
              this.editorCtx.getContents({
                success: function (o) {
                  n.num > n.maxLength
                    ? t.showToast({
                        icon: "none",
                        title: "内容不能超过" + n.maxLength + "字",
                      })
                    : ("<p><br></p>" == o.html
                        ? n.$emit("textarea", "", n.id)
                        : n.$emit("textarea", o.html, n.id),
                      (e.show = !1));
                },
              });
            },
            headleCreal: function () {
              (this.blogContent = ""),
                (this.num = 0),
                (this.editorConter = ""),
                this.editorCtx.clear();
            },
          },
        };
        e.default = n;
      }).call(this, n("df3c").default);
    },
    "6a7e": function (t, e, n) {
      "use strict";
      n.r(e);
      var o = n("13b2"),
        i = n.n(o);
      for (var r in o)
        ["default"].indexOf(r) < 0 &&
          (function (t) {
            n.d(e, t, function () {
              return o[t];
            });
          })(r);
      e.default = i.a;
    },
    8627: function (t, e, n) {
      "use strict";
      n.r(e);
      var o = n("ecb6"),
        i = n("6a7e");
      for (var r in i)
        ["default"].indexOf(r) < 0 &&
          (function (t) {
            n.d(e, t, function () {
              return i[t];
            });
          })(r);
      n("a806");
      var u = n("828b"),
        c = Object(u.a)(
          i.default,
          o.b,
          o.c,
          !1,
          null,
          "b91f8b06",
          null,
          !1,
          o.a,
          void 0,
        );
      e.default = c.exports;
    },
    a806: function (t, e, n) {
      "use strict";
      var o = n("eb79");
      n.n(o).a;
    },
    eb79: function (t, e, n) {},
    ecb6: function (t, e, n) {
      "use strict";
      n.d(e, "b", function () {
        return i;
      }),
        n.d(e, "c", function () {
          return r;
        }),
        n.d(e, "a", function () {
          return o;
        });
      var o = {
          ffPopup: function () {
            return n
              .e("components/ff-popup/ff-popup")
              .then(n.bind(null, "c29b"));
          },
          uButton: function () {
            return n
              .e("uview-ui/components/u-button/u-button")
              .then(n.bind(null, "d5d3"));
          },
        },
        i = function () {
          this.$createElement;
          this._self._c;
        },
        r = [];
    },
  },
]),
  (global.webpackJsonp = global.webpackJsonp || []).push([
    "pagesImp/components/ff-editor/ff-editor-create-component",
    {
      "pagesImp/components/ff-editor/ff-editor-create-component": function (
        t,
        e,
        n,
      ) {
        n("df3c").createComponent(n("8627"));
      },
    },
    [["pagesImp/components/ff-editor/ff-editor-create-component"]],
  ]);
