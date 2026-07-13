(global.webpackJsonp = global.webpackJsonp || []).push([
  ["pagesCourse/index/kind-reminder"],
  {
    1192: function (t, e, n) {
      "use strict";
      n.r(e);
      var o = n("eae5"),
        i = n.n(o);
      for (var c in o)
        ["default"].indexOf(c) < 0 &&
          (function (t) {
            n.d(e, t, function () {
              return o[t];
            });
          })(c);
      e.default = i.a;
    },
    2191: function (t, e, n) {},
    "2cf8": function (t, e, n) {
      "use strict";
      n.d(e, "b", function () {
        return i;
      }),
        n.d(e, "c", function () {
          return c;
        }),
        n.d(e, "a", function () {
          return o;
        });
      var o = {
          ffBottomLogo: function () {
            return n
              .e("components/ff-bottom-logo/ff-bottom-logo")
              .then(n.bind(null, "3111"));
          },
          confirmModal: function () {
            return n
              .e("components/confirm-modal/confirm-modal")
              .then(n.bind(null, "4e5b"));
          },
        },
        i = function () {
          this.$createElement;
          this._self._c;
        },
        c = [];
    },
    "6bb7": function (t, e, n) {
      "use strict";
      var o = n("2191");
      n.n(o).a;
    },
    c011: function (t, e, n) {
      "use strict";
      n.r(e);
      var o = n("2cf8"),
        i = n("1192");
      for (var c in i)
        ["default"].indexOf(c) < 0 &&
          (function (t) {
            n.d(e, t, function () {
              return i[t];
            });
          })(c);
      n("6bb7");
      var a = n("828b"),
        u = Object(a.a)(
          i.default,
          o.b,
          o.c,
          !1,
          null,
          "cf18eebc",
          null,
          !1,
          o.a,
          void 0,
        );
      e.default = u.exports;
    },
    df91: function (t, e, n) {
      "use strict";
      (function (t, e) {
        var o = n("47a9");
        n("86d2"), o(n("3240"));
        var i = o(n("c011"));
        (t.__webpack_require_UNI_MP_PLUGIN__ = n), e(i.default);
      }).call(this, n("3223").default, n("df3c").createPage);
    },
    eae5: function (t, e, n) {
      "use strict";
      (function (t) {
        Object.defineProperty(e, "__esModule", { value: !0 }),
          (e.default = void 0);
        var o = n("abae"),
          i = {
            data: function () {
              return {
                background: "#FFFFFF",
                headtitle: "温馨提示",
                title: null,
                placeholder: "请填写内容",
                blogContent: "",
                editorCtx: "",
                num: "",
                editorConter: "",
                flag: !0,
                status: "add",
              };
            },
            computed: {
              StatusBar: function () {
                return this.$store.state.systemInfo.statusBarHeight;
              },
              CustomBar: function () {
                var e = t.getMenuButtonBoundingClientRect();
                return (
                  e.height +
                  2 * (e.top - (0 !== this.StatusBar ? this.StatusBar : 20)) +
                  2
                );
              },
            },
            components: {
              confirmModal: function () {
                n.e("components/confirm-modal/confirm-modal")
                  .then(
                    function () {
                      return resolve(n("4e5b"));
                    }.bind(null, n),
                  )
                  .catch(n.oe);
              },
              navigation: function () {
                n.e("components/navigation/index")
                  .then(
                    function () {
                      return resolve(n("af9e"));
                    }.bind(null, n),
                  )
                  .catch(n.oe);
              },
            },
            onLoad: function () {
              var e = this,
                n = this;
              (0, o.getPlanHint)({}).then(function (i) {
                i.data &&
                  i.data.text &&
                  ((n.status = "edit"), (n.title = i.data.title)),
                  t
                    .createSelectorQuery()
                    .select("#editor")
                    .context(function (t) {
                      t.context.setContents({ html: i.data.text }),
                        t.context.getContents({
                          success: function (t) {
                            var e = (0, o.countLetters)(t.text),
                              i = e.english,
                              c = e.chinese;
                            n.num = Math.ceil((2 * c + i - 1) / 2);
                          },
                        }),
                        (e.editorCtx = t.context);
                    })
                    .exec();
              });
            },
            methods: {
              undo: function () {
                this.editorCtx.undo();
              },
              onInput: function (t) {
                if (1 == t.detail.text.length) this.num = 0;
                else {
                  var e = (0, o.countLetters)(t.detail.text),
                    n = e.english,
                    i = e.chinese;
                  this.num = Math.ceil((2 * i + n - 1) / 2);
                }
              },
              onBlur: function (t) {
                this.editorConter = t.detail.text;
              },
              submitBlog: function () {
                var e = this,
                  n = this;
                t.createSelectorQuery()
                  .select("#editor")
                  .context(function (i) {
                    i.context.setContents({ html: i.data }),
                      i.context.getContents({
                        success: function (i) {
                          var c = (0, o.countLetters)(i.text),
                            a = c.english,
                            u = c.chinese;
                          (n.num = Math.ceil((2 * u + a - 1) / 2)),
                            (n.blogContent = i.html),
                            n.title
                              ? n.blogContent && "<p><br></p>" != n.blogContent
                                ? e.num > 500
                                  ? t.showToast({
                                      icon: "none",
                                      title: "内容不能超过500字",
                                    })
                                  : (0, o.savePlanHint)({
                                      title: n.title,
                                      text: n.blogContent,
                                    }).then(function (e) {
                                      var n = null;
                                      200 == e.code
                                        ? ((n = "保存成功"),
                                          t.showToast({
                                            icon: "none",
                                            title: n,
                                          }),
                                          t.navigateBack({ delta: 1 }))
                                        : t.showToast({
                                            icon: "none",
                                            title: e.msg,
                                          });
                                    })
                                : t.showToast({
                                    icon: "none",
                                    title: "请填写提示内容！",
                                  })
                              : t.showToast({
                                  icon: "none",
                                  title: "请填写标题！",
                                });
                        },
                      });
                  })
                  .exec();
              },
              format: function (t) {
                var e = t.target.dataset,
                  n = e.name,
                  o = e.value;
                n && this.editorCtx.format(n, o);
              },
              headFlag: function () {
                this.flag = !0;
              },
              headleCreal: function () {
                (this.blogContent = ""),
                  (this.num = 0),
                  (this.editorConter = ""),
                  t
                    .createSelectorQuery()
                    .select("#editor")
                    .context(function (t) {
                      t.context.setContents({ html: "" });
                    })
                    .exec();
              },
              confirm: function () {
                (this.blogContent = "<p><br></p>"),
                  (this.num = 0),
                  (this.editorConter = ""),
                  t
                    .createSelectorQuery()
                    .select("#editor")
                    .context(function (e) {
                      e.context.setContents({ html: "" }),
                        (0, o.savePlanHint)({ title: "", text: "" }).then(
                          function (e) {
                            var n = null;
                            200 == e.code
                              ? ((n = "删除成功"),
                                t.navigateBack({ delta: 1 }),
                                t.showToast({ icon: "none", title: n }))
                              : t.showToast({ icon: "none", title: e.msg });
                          },
                        );
                    })
                    .exec();
              },
              headDelete: function () {
                this.$refs.confirmModal.show = !0;
              },
            },
          };
        e.default = i;
      }).call(this, n("df3c").default);
    },
  },
  [["df91", "common/runtime", "common/vendor"]],
]);
