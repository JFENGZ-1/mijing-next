(global.webpackJsonp = global.webpackJsonp || []).push([
  ["uview-ui/components/u-avatar-cropper/u-avatar-croppershop"],
  {
    1197: function (t, e, i) {
      "use strict";
      (function (t) {
        var r = i("47a9");
        Object.defineProperty(e, "__esModule", { value: !0 }),
          (e.default = void 0);
        var o = r(i("fa94")),
          n = {
            props: {
              boundStyle: {
                type: Object,
                default: function () {
                  return {
                    lineWidth: 4,
                    borderColor: "rgb(245, 245, 245)",
                    mask: "rgba(0, 0, 0, 0.35)",
                  };
                },
              },
            },
            data: function () {
              return {
                bottomNavHeight: 120,
                originWidth: 200,
                width: 0,
                height: 0,
                cropperOpt: {
                  id: "cropper",
                  targetId: "targetCropper",
                  pixelRatio: 1,
                  width: 0,
                  height: 0,
                  scale: 2.5,
                  zoom: 8,
                  cut: {
                    x: (this.width - this.originWidth) / 2,
                    y: (this.height - this.originWidth) / 2,
                    width: this.originWidth,
                    height: this.originWidth,
                  },
                  boundStyle: {
                    lineWidth: t.upx2px(this.boundStyle.lineWidth),
                    mask: this.boundStyle.mask,
                    color: this.boundStyle.borderColor,
                  },
                },
                destWidth: "",
                destHeight: "",
                rectWidth: 200,
                rectHeight: 299,
                fileType: "jpg",
                src: "",
              };
            },
            onLoad: function (e) {
              var i = this,
                r = t.getSystemInfoSync();
              if (
                ((this.width = r.windowWidth),
                (this.height = r.windowHeight - this.bottomNavHeight),
                (this.cropperOpt.width = this.width),
                (this.cropperOpt.height = this.height),
                (this.cropperOpt.pixelRatio = r.pixelRatio),
                e.destWidth &&
                  ((this.destWidth = e.destWidth),
                  (this.destHeight = e.destHeight)),
                e.rectWidth)
              ) {
                var n = Number(e.rectWidth),
                  h = Number(e.rectHeight);
                this.cropperOpt.cut = {
                  x: (this.width - n) / 2,
                  y: (this.height - h) / 2,
                  width: n,
                  height: h,
                };
              }
              (this.rectWidth = e.rectWidth),
                (this.rectHeight = e.rectHeight),
                e.fileType && (this.fileType = e.fileType),
                (this.cropper = new o.default(this.cropperOpt)
                  .on("ready", function (t) {})
                  .on("beforeImageLoad", function (t) {})
                  .on("imageLoad", function (t) {})
                  .on("beforeDraw", function (t, e) {})),
                t.setNavigationBarColor({
                  frontColor: "#ffffff",
                  backgroundColor: "#000000",
                }),
                t.chooseImage({
                  count: 1,
                  sizeType: ["original", "compressed"],
                  sourceType: ["album", "camera"],
                  success: function (t) {
                    (i.src = t.tempFilePaths[0]), i.cropper.pushOrign(i.src);
                  },
                });
            },
            methods: {
              touchStart: function (t) {
                this.cropper.touchStart(t);
              },
              touchMove: function (t) {
                this.cropper.touchMove(t);
              },
              touchEnd: function (t) {
                this.cropper.touchEnd(t);
              },
              getCropperImage: function () {
                var e = this,
                  i =
                    arguments.length > 0 &&
                    void 0 !== arguments[0] &&
                    arguments[0];
                if (!this.src) return this.$u.toast("请先选择图片再裁剪");
                var r = {
                  destHeight: Number(this.destHeight),
                  destWidth: Number(this.destWidth),
                  fileType: this.fileType,
                };
                this.cropper.getCropperImage(r, function (r, o) {
                  o
                    ? t.showModal({ title: "温馨提示", content: o.message })
                    : i
                      ? t.previewImage({ current: "", urls: [r] })
                      : (t.$emit("uAvatarCropper", r),
                        e.$u.route({ type: "back" }));
                });
              },
              uploadTap: function () {
                var e = this,
                  i = this;
                t.chooseImage({
                  count: 1,
                  sizeType: ["original", "compressed"],
                  sourceType: ["album", "camera"],
                  success: function (t) {
                    (i.src = t.tempFilePaths[0]), i.cropper.pushOrign(e.src);
                  },
                });
              },
            },
          };
        e.default = n;
      }).call(this, i("df3c").default);
    },
    "535d": function (t, e, i) {},
    "5dbb": function (t, e, i) {
      "use strict";
      i.r(e);
      var r = i("ffe0"),
        o = i("d4ae");
      for (var n in o)
        ["default"].indexOf(n) < 0 &&
          (function (t) {
            i.d(e, t, function () {
              return o[t];
            });
          })(n);
      i("60d8");
      var h = i("828b"),
        c = Object(h.a)(
          o.default,
          r.b,
          r.c,
          !1,
          null,
          "a1b29062",
          null,
          !1,
          r.a,
          void 0,
        );
      e.default = c.exports;
    },
    "60d8": function (t, e, i) {
      "use strict";
      var r = i("535d");
      i.n(r).a;
    },
    "952a": function (t, e, i) {
      "use strict";
      (function (t, e) {
        var r = i("47a9");
        i("86d2"), r(i("3240"));
        var o = r(i("5dbb"));
        (t.__webpack_require_UNI_MP_PLUGIN__ = i), e(o.default);
      }).call(this, i("3223").default, i("df3c").createPage);
    },
    d4ae: function (t, e, i) {
      "use strict";
      i.r(e);
      var r = i("1197"),
        o = i.n(r);
      for (var n in r)
        ["default"].indexOf(n) < 0 &&
          (function (t) {
            i.d(e, t, function () {
              return r[t];
            });
          })(n);
      e.default = o.a;
    },
    ffe0: function (t, e, i) {
      "use strict";
      i.d(e, "b", function () {
        return r;
      }),
        i.d(e, "c", function () {
          return o;
        }),
        i.d(e, "a", function () {});
      var r = function () {
          this.$createElement;
          this._self._c;
        },
        o = [];
    },
  },
  [["952a", "common/runtime", "common/vendor"]],
]);
