(global.webpackJsonp = global.webpackJsonp || []).push([
  ["pages/tailor/u-avatar-cropper/u-avatar-cropper"],
  {
    "04aa": function (t, e, i) {
      i.r(e);
      var o = i("cc01"),
        r = i.n(o);
      for (var n in o)
        ["default"].indexOf(n) < 0 &&
          (function (t) {
            i.d(e, t, function () {
              return o[t];
            });
          })(n);
      e.default = r.a;
    },
    "57a3": function (t, e, i) {
      var o = i("a5d8");
      i.n(o).a;
    },
    "5e93": function (t, e, i) {
      i.r(e);
      var o = i("b0f0"),
        r = i("04aa");
      for (var n in r)
        ["default"].indexOf(n) < 0 &&
          (function (t) {
            i.d(e, t, function () {
              return r[t];
            });
          })(n);
      i("57a3");
      var a = i("828b"),
        c = Object(a.a)(
          r.default,
          o.b,
          o.c,
          !1,
          null,
          "43c419db",
          null,
          !1,
          o.a,
          void 0,
        );
      e.default = c.exports;
    },
    "672f": function (t, e, i) {
      (function (t, e) {
        var o = i("47a9");
        i("9785"), o(i("3240"));
        var r = o(i("5e93"));
        (t.__webpack_require_UNI_MP_PLUGIN__ = i), e(r.default);
      }).call(this, i("3223").default, i("df3c").createPage);
    },
    a5d8: function (t, e, i) {},
    b0f0: function (t, e, i) {
      i.d(e, "b", function () {
        return o;
      }),
        i.d(e, "c", function () {
          return r;
        }),
        i.d(e, "a", function () {});
      var o = function () {
          this.$createElement;
          this._self._c;
        },
        r = [];
    },
    cc01: function (t, e, i) {
      (function (t) {
        var o = i("47a9");
        Object.defineProperty(e, "__esModule", { value: !0 }),
          (e.default = void 0);
        var r = o(i("d652")),
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
                destWidth: 200,
                rectWidth: 200,
                fileType: "jpg",
                src: "",
              };
            },
            onLoad: function (e) {
              var i = this,
                o = t.getSystemInfoSync();
              if (
                ((this.width = o.windowWidth),
                (this.height = o.windowHeight - this.bottomNavHeight),
                (this.cropperOpt.width = this.width),
                (this.cropperOpt.height = this.height),
                (this.cropperOpt.pixelRatio = o.pixelRatio),
                e.destWidth && (this.destWidth = e.destWidth),
                e.rectWidth)
              ) {
                var n = Number(e.rectWidth);
                this.cropperOpt.cut = {
                  x: (this.width - n) / 2,
                  y: (this.height - n) / 2,
                  width: n,
                  height: n,
                };
              }
              (this.rectWidth = e.rectWidth),
                e.fileType && (this.fileType = e.fileType),
                (this.cropper = new r.default(this.cropperOpt)
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
                  sizeType: ["compressed"],
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
                var o = {
                  destHeight: Number(this.destWidth),
                  destWidth: Number(this.destWidth),
                  fileType: this.fileType,
                };
                this.cropper.getCropperImage(o, function (o, r) {
                  r
                    ? t.showModal({ title: "温馨提示", content: r.message })
                    : i
                      ? t.previewImage({ current: "", urls: [o] })
                      : (t.$emit("uAvatarCropper", o),
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
  },
  [["672f", "common/runtime", "common/vendor"]],
]);
